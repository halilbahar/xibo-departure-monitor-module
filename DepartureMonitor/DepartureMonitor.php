<?php

namespace Xibo\Custom\DepartureMonitor;

use stdClass;
use Xibo\Widget\ModuleWidget;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;


class DepartureMonitor extends ModuleWidget {


    public function installOrUpdate($moduleFactory) {
        if ($this->module == null) {
            // Install
            $module = $moduleFactory->createEmpty();
            $module->name = 'Departure-Monitor';
            $module->type = 'departuremonitor';
            $module->class = 'Xibo\Custom\DepartureMonitor\DepartureMonitor';
            $module->description = 'A module for displaying Departure-Monitors.';
            $module->imageUri = 'forms/library.gif';
            $module->enabled = 1;
            $module->previewEnabled = 1;
            $module->assignable = 1;
            $module->regionSpecific = 1;
            $module->renderAs = 'html';
            $module->schemaVersion = $this->codeSchemaVersion;
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

        //Install files from a folder
        $folder = PROJECT_ROOT . '/custom/DepartureMonitor/resources';
        foreach ($this->mediaFactory->createModuleFileFromFolder($folder) as $media) {
            $media->save();
        }
    }

    public function add() {
        $this->change();
    }

    public function edit() {
        $this->change();
    }

    public function change() {
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
        $this->setOption('fontFamily', $this->getSanitizer()->getString('fontFamily'));
        $this->setOption('theadBackgroundColor', $this->getSanitizer()->getString('theadBackgroundColor'));
        $this->setOption('theadFontColor', $this->getSanitizer()->getString('theadFontColor'));
        $this->setOption('tbodyFontColor', $this->getSanitizer()->getString('tbodyFontColor'));
        $this->setOption('trBackgroundColor', $this->getSanitizer()->getString('trBackgroundColor'));
    }

    public function layoutDesignerJavaScript() {
        return 'departuremonitor-designer-javascript';
    }

    public function getResource($displayId = 0) {
        //Get image URLs
        $tram = $this->getResourceUrl('bim.png');
        $bus = $this->getResourceUrl('bus.png');
        $citybus = $this->getResourceUrl('citybus.png');
        $train = $this->getResourceUrl('train.png');
        $underground = $this->getResourceUrl('underground.png');

        //Get the destination string and turn it into an array
        $destinations = preg_split('@;@', $this->getOption('destination'), NULL, PREG_SPLIT_NO_EMPTY);
        //
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
            $timeA = $a->arrivalTime->hour * 60 + $a->arrivalTime->minute;
            $timeB = $b->arrivalTime->hour * 60 + $b->arrivalTime->minute;
            return $timeA < $timeB ? -1 : 1;
        });

        $font = $this->getOption('fontFamily');
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
                let trBackgroundColor = "' . $this->getOption('trBackgroundColor') . '";
            ')
            ->appendJavaScriptFile('dm_script.js')
            ->appendFontCss()
            ->appendCssFile('departure_monitor.css')
            ->appendCss('
                body {
                    font-family: ' . (!empty($font) ? $font . ',' : '') . ' Arial, sans-serif;;
                }
                thead tr{
                    background-color: ' . $this->getOption('theadBackgroundColor') . ';
                    color: ' . $this->getOption('theadFontColor') . ';
                }
                tbody {
                    color: ' . $this->getOption('tbodyFontColor') . ';
                }
            ')
            ->appendBody("<div id='wrapper'>
                    <table id='traffic-schedule'>
                        <thead>
                            <tr>
                                <th id='tbl-head1'></th>
                                <th id='tbl-head2'>Linie</th>
                                <th id='tbl-head3'>Von</th>
                                <th id='tbl-head4'>Nach</th>
                                <th id='tbl-head5'>Ab</th>
                                <th id='tbl-head6'>verbleibend</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>");
        return $this->finaliseGetResource();
    }

    public function isValid() {
        return 1;
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
            $entry->arrivalTime = new  stdClass();
            $entry->arrivalTime->hour = (int)$departure->dateTime->hour;
            $entry->arrivalTime->minute = (int)$departure->dateTime->minute;
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
                $entry->arrivalTime = new stdClass();

                $arrivalTime = strtotime($departure->departureTime->timePlanned);
                $entry->arrivalTime->hour = (int)date('H', $arrivalTime);
                $entry->arrivalTime->minute = (int)date('i', $arrivalTime);

                $data[] = $entry;
            }
        }

        return $data;
    }
}