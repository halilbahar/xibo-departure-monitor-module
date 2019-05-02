<?php

namespace Xibo\Custom\DepartureMonitor;

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

    /**
     * Install Files
     */
    public function installFiles() {
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/jquery-1.11.1.min.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-text-render.js')->save();

        //Install files from a folder
        $folder = PROJECT_ROOT . '/custom/DepartureMonitor/resources';
        foreach ($this->mediaFactory->createModuleFileFromFolder($folder) as $media) {
            $media->save();
        }
    }


    public function add() {
        $this->setCommonOptions();
        // Save the widget
        $this->isValid();
        $this->saveWidget();
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
        $this->setOption('limit', $this->getSanitizer()->getInt('limit'));
        $this->setOption('fontFamily', $this->getSanitizer()->getString('fontFamily'));
    }

    public function layoutDesignerJavaScript() {
        return 'departuremonitor-designer-javascript';
    }

    public function getResource($displayId = 0) {

        $isPreview = $this->getSanitizer()->getCheckbox('preview') == 1;

        $tramId = $this->mediaFactory->getByName('bim.png')->mediaId;
        $busId = $this->mediaFactory->getByName('bus.png')->mediaId;

        $this->assignMedia($tramId);
        $this->assignMedia($busId);

        $tram = $isPreview ? $this->getResourceUrl('bim.png') : $tramId . '.png';
        $bus = $isPreview ? $this->getResourceUrl('bus.png') : $busId . '.png';

        $destinations = preg_split('@;@', $this->getOption('destination'), NULL, PREG_SPLIT_NO_EMPTY);
        $jsonData = "";
        switch ($this->getOption('serviceId', 1)) {
            //LinzAG
            case 1:
                $jsonData = $this->getLinzAGData($destinations);
                break;
            //Wiener Linien
            case 2:
                $jsonData = $this->getWienerLinienData($destinations);
        }

        // Start building the template
        $this
            ->initialiseGetResource()
            ->appendViewPortWidth($this->region->width)
            ->appendJavaScriptFile('vendor/jquery-1.11.1.min.js')
            ->appendJavaScript('
                let data = ' . json_encode($jsonData) . ';
                let bus = "' . $bus . '";
                let tram = "' . $tram . '";
            ')
            ->appendJavaScriptFile('dm_script.js')
            ->appendFontCss()
            ->appendCssFile('departure_monitor.css')
            ->appendBody("<div id='wrapper'>
                    <table id='traffic-schedule'>
                        <thead>
                            <tr>
                                <th id='tbl-head1' width='15%'></th>
                                <th id='tbl-head2' width='10%' style='text-align:right; padding-right: 5%'>Linie</th>
                                <th id='tbl-head3' width='25%' style='text-align:left; padding-left: 0%;'>Von</th>
                                <th id='tbl-head4' width='25%' style='text-align:left; padding-left: 0%;'>Nach</th>
                                <th id='tbl-head5' width='12.5%' style='text-align:left;'>Ab</th>
                                <th id='tbl-head6' width='12.5%' style='text-align:right; padding-right: 4%;'>verbleibend</th>
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

    public function getLinzAGData($destinations) {
        $depatureList = array();
        $limit = $this->getOption('limit');

        foreach ($destinations as $singleDestination) {
            $sessionIDUrl = 'http://www.linzag.at/static/XML_DM_REQUEST?sessionID=0&locationServerActive=1&type_dm=stop&name_dm=' . $singleDestination . '&outputFormat=JSON&limit=';
            $sessionID = $this->requstGetJSON($sessionIDUrl)->parameters[1]->value;

            $departureMonitorUrl = 'http://www.linzag.at/static/XML_DM_REQUEST?sessionID=' . $sessionID . '&requestID=1&dmLineSelectionAll=1';
            $departureMontior = $this->requstGetJSON($departureMonitorUrl)->departureList;

            $depatureList = array_merge($depatureList, $departureMontior);
        }

        usort($depatureList, function ($a, $b) { //Sort the array using a user defined function
            return $a->countdown < $b->countdown ? -1 : 1; //Compare the scores
        });

        $data = array();
        for ($i = 0; $i < $limit; $i++) {
            if (isset($depatureList[$i])) {
                $entry = new \stdClass();
                $entry->type = $depatureList[$i]->servingLine->name;
                $entry->number = $depatureList[$i]->servingLine->number;
                $entry->from = $depatureList[$i]->nameWO;
                $entry->to = $depatureList[$i]->servingLine->direction;
                $entry->arrivalTime = new  \stdClass();
                $entry->arrivalTime->hour = (int)$depatureList[$i]->dateTime->hour;
                $entry->arrivalTime->minute = (int)$depatureList[$i]->dateTime->minute;
                $data[] = $entry;
            }
        }
        return $data;
    }

    public function getWienerLinienData($destinations) {

        $stops = $this->getCsvAs2DArray('https://data.wien.gv.at/csv/wienerlinien-ogd-haltestellen.csv');
        $stopIDs = $this->findCsvColumnByColumn($stops, $destinations, 'NAME', 'HALTESTELLEN_ID');

        $rbl = $this->getCsvAs2DArray('https://data.wien.gv.at/csv/wienerlinien-ogd-steige.csv');
        $RBLNumbers = $this->findCsvColumnByColumn($rbl, $stopIDs, 'FK_HALTESTELLEN_ID', 'RBL_NUMMER');

        $RBLString = '';
        foreach ($RBLNumbers as $RBLNumber) {
            $RBLString .= '&rbl=' . $RBLNumber;
        }

        try {
            $client = new Client($this->getConfig()->getGuzzleProxy());
            $key = '<Key für Wiener Linien>';
            $url = 'http://www.wienerlinien.at/ogd_realtime/monitor?sender=' . $key . $RBLString;
            $response = $client->request('GET', $url);

            $result = json_decode($response->getBody());

            $data = array();
            foreach ($result->data->monitors as $monitor) {
                foreach ($monitor->lines[0]->departures->departure as $departure) {
                    $entry = new \stdClass();
                    $entry->type = $monitor->lines[0]->type;
                    $entry->number = $monitor->lines[0]->name;
                    $entry->from = $monitor->locationStop->properties->title;
                    $entry->to = $monitor->lines[0]->towards;
                    $entry->arrivalTime = new \stdClass();

                    $arrivalTime = strtotime($departure->departureTime->timePlanned);
                    $entry->arrivalTime->hour = (int)date('H', $arrivalTime);
                    $entry->arrivalTime->minute = (int)date('i', $arrivalTime);

                    $data[] = $entry;
                }
            }

            usort($data, function ($a, $b) {
                $timeA = $a->arrivalTime->hour * 60 + $a->arrivalTime->minute;
                $timeB = $b->arrivalTime->hour * 60 + $b->arrivalTime->minute;
                return $timeA < $timeB ? -1 : 1;
            });

            return $data;
        } catch (RequestException $requestException) {
            $this->getLog()->error('Wiener Linien API Request returned ' . $requestException->getMessage() . ' status. Unable to proceed.');
            return false;
        }
    }

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

    public function requstGetJSON($url){
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

    public function getCacheDuration() {
        return 1;
    }
}