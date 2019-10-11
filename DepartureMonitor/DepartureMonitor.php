<?php

namespace Xibo\Custom\DepartureMonitor;

use DateTime;
use stdClass;
use Xibo\Widget\ModuleWidget;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Xibo\Exception\InvalidArgumentException;

class DepartureMonitor extends ModuleWidget {


    public function installOrUpdate($moduleFactory) {
        if ($this->module == null) {
            // Install
            $module = $moduleFactory->createEmpty();
            $module->name = 'Departure-Monitor';
            $module->type = 'departuremonitor';
            $module->class = 'Xibo\Custom\DepartureMonitor\DepartureMonitor';
            $module->description = 'A module for displaying Departure-Monitors.';
            $module->enabled = 1;
            $module->previewEnabled = 1;
            $module->assignable = 1;
            $module->regionSpecific = 1;
            $module->renderAs = 'html';
            $module->schemaVersion = 1;
            $module->defaultDuration = 60;
            $module->settings = [];
            $module->viewPath = '../custom/DepartureMonitor';

            // Set the newly created module and then call install
            $this->setModule($module);
            $this->installModule();
        }

        // Install and additional module files that are required.
        $this->installFiles();
    }

    public function installFiles() {
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/jquery-1.11.1.min.js')->save();

        //Install resource files
        $folder = PROJECT_ROOT . '/custom/DepartureMonitor/resources';
        foreach ($this->mediaFactory->createModuleFileFromFolder($folder) as $media) {
            $media->save();
        }

        //Install image files
        $folder = PROJECT_ROOT . '/custom/DepartureMonitor/resources/images';
        foreach ($this->mediaFactory->createModuleFileFromFolder($folder) as $media) {
            $media->save();
        }
    }

    public function edit() {
        $this->setCommonOptions();
        // Save the widget
        $this->isValid();
        $this->saveWidget();
    }


    private function setCommonOptions() {
        $this->setDuration($this->getSanitizer()->getInt('duration', $this->getDuration()));
        $this->setUseDuration($this->getSanitizer()->getCheckbox('useDuration'));
        $this->setOption('serviceId', $this->getSanitizer()->getInt('serviceId', 1));
        $this->setOption('name', $this->getSanitizer()->getString('name'));
        $this->setOption('destination', $this->getSanitizer()->getString('destination'));
        $this->setOption('apiKey', $this->getSanitizer()->getString('apiKey'));
        $this->setOption('bodyFont', $this->getSanitizer()->getString('bodyFont'));
        $this->setOption('theadBackgroundColor', $this->getSanitizer()->getString('theadBackgroundColor'));
        $this->setOption('theadFontColor', $this->getSanitizer()->getString('theadFontColor'));
        $this->setOption('tbodyFontColor', $this->getSanitizer()->getString('tbodyFontColor'));
        $this->setOption('tbodyBackgroundColor', $this->getSanitizer()->getString('tbodyBackgroundColor'));
        $this->setOption('tbodySecondBackgroundColor', $this->getSanitizer()->getString('tbodySecondBackgroundColor'));
        $this->setOption('headFont', $this->getSanitizer()->getString('headFont'));
        $this->setOption('hideIcons', $this->getSanitizer()->getCheckbox('hideIcons'));
        $this->setOption('reverseIcons', $this->getSanitizer()->getCheckbox('reverseIcons'));
        $this->setOption('lineHeader', $this->getSanitizer()->getString('lineHeader'));
        $this->setOption('fromHeader', $this->getSanitizer()->getString('fromHeader'));
        $this->setOption('toHeader', $this->getSanitizer()->getString('toHeader'));
        $this->setOption('startHeader', $this->getSanitizer()->getString('startHeader'));
        $this->setOption('remainingHeader', $this->getSanitizer()->getString('remainingHeader'));
        $this->setOption('theadFontScale', $this->getSanitizer()->getString('theadFontScale'));
        $this->setOption('tbodyFontScale', $this->getSanitizer()->getString('tbodyFontScale'));
        $this->setOption('rowCount', $this->getSanitizer()->getString('rowCount'));
        $this->setOption('hideHeader', $this->getSanitizer()->getCheckbox('hideHeader'));
        $this->setOption('minuteLimit', $this->getSanitizer()->getString('minuteLimit'));
    }

    public function layoutDesignerJavaScript() {
        return 'departuremonitor-designer-javascript';
    }

    public function getResource($displayId = 0) {
        $iconSuffix = $this->getOption('reverseIcons') ? '_w.png' : '_b.png';
        //Get image URLs
        $tram = $this->getResourceUrl('tram' . $iconSuffix);
        $bus = $this->getResourceUrl('bus' . $iconSuffix);
        $citybus = $this->getResourceUrl('citybus' . $iconSuffix);
        $train = $this->getResourceUrl('train' . $iconSuffix);
        $underground = $this->getResourceUrl('underground' . $iconSuffix);

        //Get the destination string and turn it into an array
        $destinations = preg_split('@;@', $this->getOption('destination'), NULL, PREG_SPLIT_NO_EMPTY);
        $key = $this->getOption('apiKey');

        //Look up what api was selected. Get JSON array from that api
        $jsonData = "";
        switch ($this->getOption('serviceId', 1)) {
            //LinzAG
            case 1:
                $jsonData = $this->getLinzAGData($destinations);
                break;
            //Wiener Linien
            case 2:
                $jsonData = $this->getWienerLinienData($destinations, $key);
                break;
        }

        //Sort Monitor after getting it
        usort($jsonData, function ($a, $b) {
            $timeA = new DateTime($a->arrivalTime);
            $timeB = new DateTime($b->arrivalTime);
            return $timeA < $timeB ? -1 : 1;
        });

        $tableHeadIcons = '
            <td class="row-10"></td>
            <td class="row-10">' . $this->getOption('lineHeader') . '</td>
            <td class="row-24">' . $this->getOption('fromHeader') . '</td>
            <td class="row-26 td-align-right-padding-3">' . $this->getOption('toHeader') . '</td>
            <td class="row-15 td-align-center">' . $this->getOption('startHeader') . '</td>
            <td class="row-15 td-align-right-padding-3">' . $this->getOption('remainingHeader') . '</td>
        ';

        $tableHeadNoIcons = '
            <td class="td-empty"></td>
            <td class="row-15 td-padding-left-3">' . $this->getOption('lineHeader') . '</td>
            <td class="row-27-5">' . $this->getOption('fromHeader') . '</td>
            <td class="row-27-5 td-align-right-padding-3">' . $this->getOption('toHeader') . '</td>
            <td class="row-15 td-align-center">' . $this->getOption('startHeader') . '</td>
            <td class="row-15 td-align-right-padding-3">' . $this->getOption('remainingHeader') . '</td>
        ';

        $headerHeight = $this->getOption('hideHeader') ? 0 : 8;
        $rowHeight = $this->getOption('rowCount') ? (100 - $headerHeight) / $this->getOption('rowCount') : 0;

        $dataClasses = $this->getOption('hideIcons') ?
            '"td-empty", "row-15", "row-27-5", "row-27-5", "row-15", "row-15"' :
            '"row-10", "row-10", "row-24", "row-26", "row-15", "row-15"';

        // Start building the template
        $this
            ->initialiseGetResource()
            ->appendViewPortWidth($this->region->width)
            ->appendJavaScriptFile('vendor/jquery-1.11.1.min.js')
            ->appendJavaScript('
                let data = ' . json_encode($jsonData) . ';
                let tram = "' . $tram . '";
                let motorbus = "' . $bus . '";
                let citybus = "' . $citybus . '";
                let train = "' . $train . '";
                let underground = "' . $underground . '";
                let tbodySecondBackgroundColor = "' . $this->getOption('tbodySecondBackgroundColor') . '";
                let hideIcons = ' . ($this->getOption('hideIcons') == 0 ? 'false' : 'true') . ';
                let dataClasses = [' . $dataClasses . '];
                let minuteLimit = ' . $this->getOption('minuteLimit') . ';
            ')
            ->appendJavaScriptFile('dm_script.js')
            ->appendFontCss()
            ->appendCssFile('departure_monitor.css')
            ->appendCss('                
                #table-main tbody {
                    font-family: ' . $this->getOption('bodyFont') . ', sans-serif;
                    color: ' . $this->getOption('tbodyFontColor') . ';
                    font-size: ' . $this->getOption('tbodyFontScale') . 'em;
                }
                
                #table-main .tr-content {
                    background-color: ' . $this->getOption('tbodyBackgroundColor') . ';
                }
                
                #table-main thead {
                    font-family: ' . $this->getOption('headFont') . ', sans-serif;
                    color: ' . $this->getOption('theadFontColor') . ';
                    font-size: ' . $this->getOption('theadFontScale') . 'em;
                    display: ' . ($this->getOption('hideHeader') ? 'none' : '') .';
                }
                
                #table-main thead tr {
                    background-color: ' . $this->getOption('theadBackgroundColor') . ';
                }
                
                .div-height {
                    height: ' . $rowHeight . 'vh;
                    padding: 5% 0;
                    display: flex;
                    align-items: center;
                }
                
            ')
            ->appendBody('
                <table id="table-main">
                    <thead>
                        <tr>
                            ' . ($this->getOption('hideIcons') ? $tableHeadNoIcons : $tableHeadIcons) . '
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            ');
        return $this->finaliseGetResource();
    }

    public function isValid() {
        if ($this->getUseDuration() == 1 && $this->getDuration() <= 0) {
            throw new InvalidArgumentException(__('You must enter a duration.'), 'duration');
        }

        $theadFontScale = str_replace(",", ".", $this->getOption('theadFontScale'));
        if (!is_numeric($theadFontScale)) {
            throw new InvalidArgumentException(__('You must enter a number for the head font multiplier'), 'theadFontScale');
        } else if ($theadFontScale < 0) {
            throw new InvalidArgumentException(__('You must enter a positiv scale for the head font multiplier'), 'theadFontScale');
        }

        $tbodyFontScale = str_replace(",", ".", $this->getOption('tbodyFontScale'));
        if (!is_numeric($tbodyFontScale)) {
            throw new InvalidArgumentException(__('You must enter a number for the body font multiplier'), 'tbodyFontScale');
        } else if ($tbodyFontScale < 0) {
            throw new InvalidArgumentException(__('You must enter a positiv scale for the body font multiplier'), 'tbodyFontScale');
        }

        $rowCount = $this->getOption('rowCount');
        if (!is_numeric($rowCount)) {
            throw new InvalidArgumentException(__('You must enter a number for the row count'), 'tbodyFontScale');
        } else if (!ctype_digit($rowCount)) {
            if ($rowCount[0] == '-') {
                throw new InvalidArgumentException(__('You must enter a positiv number for the row count'), 'rowCount');
            } else {
                throw new InvalidArgumentException(__('You must enter an integer for the row count'), 'rowCount');
            }
        }

        $minuteLimit = $this->getOption('minuteLimit');
        if (!is_numeric($minuteLimit)) {
            throw new InvalidArgumentException(__('You must enter a number for the minute limit'), 'minuteLimit');
        } else if (!ctype_digit($rowCount)) {
            if ($rowCount[0] == '-') {
                throw new InvalidArgumentException(__('You must enter a positiv number for the minute limit'), 'minuteLimit');
            } else {
                throw new InvalidArgumentException(__('You must enter an integer for the minute limit'), 'minuteLimit');
            }
        }

        return self::$STATUS_PLAYER;
    }

    public function getCacheDuration() {
        return 1;
    }

    //////////////////////
    /// Util-Functions ///
    //////////////////////

    public function getCsvAs2DArray($url) {
        try {
            $client = new Client($this->getConfig()->getGuzzleProxy());
            $csv = $client->request('GET', $url);

            $lines = explode(PHP_EOL, $csv->getBody());
            $head = str_getcsv(array_shift($lines), ';');

            $array = array();
            foreach ($lines as $line) {
                $row = array_pad(str_getcsv($line, ';'), count($head), '');
                $array[] = array_combine($head, $row);
            }

            return $array;

        } catch (RequestException $requestException) {
            $this->getLog()->error('Wiener Linien CSV Request returned ' . $requestException->getMessage() . ' status. Unable to proceed.');
            return false;
        }
    }

    public function findCsvColumnByColumn($csv, $values, $serachColumn, $returnColumn) {
        $result = array();
        foreach ($csv as $row) {
            foreach ($values as $value) {
                if (strtolower($value) == strtolower($row[$serachColumn])) {
                    if ($row[$returnColumn] != '') {
                        $result[] = $row[$returnColumn];
                    }
                }
            }
        }
        return $result;
    }

    public function requstGetJSON($url) {
        try {
            $client = new Client($this->getConfig()->getGuzzleProxy());
            $response = $client->request('GET', $url);

            $result = json_decode($response->getBody()->getContents());

            return $result;

        } catch (RequestException $requestException) {
            $this->getLog()->error('Departure-Monitor returned ' . $requestException->getMessage() . ' status. Unable to proceed.');
            return false;
        }
    }

    public function getLeadingZero($number) {
        return $number < 10 ? "0" . $number : $number;
    }

    //////////////
    /// LinzAG ///
    //////////////

    public function getLinzAGData($destinations) {
        $depatureList = array();

        foreach ($destinations as $singleDestination) {
            //Request for a session id
            $sessionIDUrl = 'http://www.linzag.at/static/XML_DM_REQUEST?sessionID=0&locationServerActive=1&type_dm=stop&name_dm=' . $singleDestination . '&outputFormat=JSON';
            $sessionID = $this->requstGetJSON($sessionIDUrl)->parameters[1]->value;

            //Use the session id to request the departure monitor
            $departureMonitorUrl = 'http://www.linzag.at/static/XML_DM_REQUEST?sessionID=' . $sessionID . '&requestID=1&dmLineSelectionAll=1';
            $departureMontior = $this->requstGetJSON($departureMonitorUrl)->departureList;

            //Put it in the results if a departure monitor was returned
            if (isset($departureMontior)) {
                $depatureList = array_merge($depatureList, $departureMontior);
            }
        }

        //Create a json array
        $data = array();
        foreach ($depatureList as $departure) {
            $entry = new stdClass();
            switch ($departure->servingLine->name) {
                case 'Straßenbahn':
                    $entry->type = 'tram';
                    break;
                case 'Stadtteilbus':
                    $entry->type = 'citybus';
                    break;
                case 'Obus':
                case 'Autobus':
                    $entry->type = 'motorbus';
                    break;
                default:
                    $entry->type = '';
            }
            $entry->number = $departure->servingLine->number;
            $entry->from = $departure->nameWO;
            $entry->to = $departure->servingLine->direction;
            $entry->arrivalTime =
                $departure->dateTime->year . "-" .
                $this->getLeadingZero($departure->dateTime->month) . "-" .
                $this->getLeadingZero($departure->dateTime->day) . "T" .
                $this->getLeadingZero($departure->dateTime->hour) . ":" .
                $this->getLeadingZero($departure->dateTime->minute) . ":00+02:00";
            $data[] = $entry;
        }
        return $data;
    }

    /////////////////////
    /// Wiener Linien ///
    /////////////////////

    public function getWienerLinienData($destinations, $key) {
        //Get stop-csv and see if the destinations exist and get their id
        $stops = $this->getCsvAs2DArray('https://data.wien.gv.at/csv/wienerlinien-ogd-haltestellen.csv');
        $stopIDs = $this->findCsvColumnByColumn($stops, $destinations, 'NAME', 'HALTESTELLEN_ID');

        //Get the RBL numbers from the ids
        $rbl = $this->getCsvAs2DArray('https://data.wien.gv.at/csv/wienerlinien-ogd-steige.csv');
        $RBLNumbers = $this->findCsvColumnByColumn($rbl, $stopIDs, 'FK_HALTESTELLEN_ID', 'RBL_NUMMER');

        //Build the rbl parameters with their values
        $RBLString = '';
        foreach ($RBLNumbers as $RBLNumber) {
            $RBLString .= '&rbl=' . $RBLNumber;
        }

        $url = 'http://www.wienerlinien.at/ogd_realtime/monitor?sender=' . $key . $RBLString;
        $result = $this->requstGetJSON($url);

        //Create json array
        $data = array();
        foreach ($result->data->monitors as $monitor) {
            foreach ($monitor->lines[0]->departures->departure as $departure) {
                $entry = new stdClass();
                switch ($monitor->lines[0]->type) {
                    case 'ptMetro':
                        $entry->type = 'underground';
                        break;
                    case 'ptTram':
                        $entry->type = 'tram';
                        break;
                    case 'ptBusCity':
                        $entry->type = 'motorbus';
                        break;
                    default:
                        $entry->type = '';
                        break;
                }
                $entry->number = $monitor->lines[0]->name;
                $entry->from = $monitor->locationStop->properties->title;
                $entry->to = $monitor->lines[0]->towards;
                $entry->arrivalTime = (new DateTime($departure->departureTime->timePlanned))->format(DateTime::ATOM);

                $data[] = $entry;
            }
        }

        return $data;
    }
}
