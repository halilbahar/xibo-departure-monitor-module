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

        // Start building the template
        $this
            ->initialiseGetResource()
            ->appendViewPortWidth($this->region->width)
            ->appendJavaScriptFile('vendor/jquery-1.11.1.min.js')
            ->appendJavaScript('
                $(function () {
                    let data = ' . json_encode($this->getLinzAGData()) . '
            
                    //Look for expired entries, if you find one delete it
                    let currentDate = new Date();
                    let currentMinutes = currentDate.getHours() * 60 + currentDate.getMinutes();
                    let index = 0;
                    while (index < data.length) {
                        if (data[index].arrivalTime.hour * 60 + data[index].arrivalTime.minute - currentMinutes < 0) {
                            data.splice(index, 1);
                        } else {
                            index++;
                        }
                    }
            
                    //Generate rows for every entry
                    let table = document.getElementById("traffic-schedule");
                    for (let i = 0; i < data.length; i++) {
                        let tr = table.getElementsByTagName(\'tbody\')[0].insertRow(-1);
                        let td = [];
                        for (let j = 0; j < 6; j++) {
                            td[j] = tr.insertCell(j);
                            td[j].classList.add("column" + (j + 1));
                        }
                        let hour = data[i].arrivalTime.hour;
                        let minute = data[i].arrivalTime.minute;
                        td[0].innerHTML = "<img src=\'" + getImageSrc(data[i].type) + "\'>";
                        td[1].innerHTML = data[i].number;
                        td[2].innerHTML = data[i].from;
                        td[3].innerHTML = data[i].to;
                        td[4].innerHTML = (hour < 10 ? "0" + hour : hour) + ":" + (minute < 10 ? "0" + minute : minute);
                        let entryTime = hour * 60 + minute;
                        td[5].innerHTML = entryTime - currentMinutes;
                    }
            
                    //Set the backgroundcolor of every second row
                    colorBackground(table.rows);
            
                    let nextMinuteDate = new Date();
                    nextMinuteDate.setMinutes(currentDate.getMinutes() + 1, 0, 0);
                    let waitTime = nextMinuteDate.getTime() - currentDate.getTime();
                    //Wait for the minute to finish and count down
                    setTimeout(() => {
                        countDown();
                        //Count down every minute if entry has been expired, animate it out
                        setInterval(countDown, 1000 * 60);
                    }, waitTime);
                });
            
                function countDown() {
                    let tableRows = document.getElementById("traffic-schedule").rows;
                    let minuteIndex = 5;
                    for (let i = 1; i < tableRows.length; i++) {
                        if (parseInt(tableRows[i].cells[minuteIndex].innerHTML) === 0) {
                            $("#traffic-schedule tr:eq(" + i + ")")
                                .children("td")
                                .animate({paddingBottom: 0, paddingTop: 0})
                                .wrapInner("<div />")
                                .children()
                                .slideUp(function () {
                                    $(this).closest("tr").remove();
                                });
                        } else {
                            tableRows[i].cells[minuteIndex].innerHTML--;
                        }
                    }
                }
            
                function colorBackground(rows) {
                    for (let i = 0; i < rows.length; i++) {
                        if (i % 2 === 0) {
                            rows[i].style.backgroundColor = "#f5f5f5";
                        }
                    }
                }
            
                function getImageSrc(type) {
                    let src = "";
                    switch (type) {
                        case "Straßenbahn":
                            src = "' . $tram . '";
                            break;
                        case "Autobus":
                        case "Stadtteilbus":
                            src = "' . $bus . '";
                            break;
                        default:
                            src = "";
                    }
                    return src;
                }
            ')
            ->appendFontCss()
            ->appendCss($this->getCss())
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

    public function getSessionID($destination, $limit) {
        try {
            $client = new Client($this->getConfig()->getGuzzleProxy());
            $url = 'http://www.linzag.at/static/XML_DM_REQUEST?sessionID=0&locationServerActive=1&type_dm=stop&name_dm=' . $destination . '&outputFormat=JSON&limit=' . $limit;
            $response = $client->request('GET', $url);

            $result = json_decode($response->getBody()->getContents());

            return $result->parameters[1]->value;
        } catch (RequestException $requestException) {
            $this->getLog()->error('LinzAG API returned ' . $requestException->getMessage() . ' status. Unable to proceed.');
            return false;
        }
    }

    public function getLinzAGDepatureMonitor($destination, $limit) {
        try {
            $client = new Client($this->getConfig()->getGuzzleProxy());
            $url = 'http://www.linzag.at/static/XML_DM_REQUEST?sessionID=' . $this->getSessionID($destination, $limit) . '&requestID=1&dmLineSelectionAll=1';
            $response = $client->request('GET', $url);

            $result = json_decode($response->getBody()->getContents());

            return $result->departureList;
        } catch (RequestException $requestException) {
            $this->getLog()->error('LinzAG API returned ' . $requestException->getMessage() . ' status. Unable to proceed.');
            return false;
        }
    }

    public function getLinzAGData() {
        $destinations = explode(";", $this->getOption('destination'));
        $depatureList = array();
        $limit = $this->getOption('limit');

        foreach ($destinations as $singleDestination) {
            $depatureList = array_merge($depatureList, $this->getLinzAGDepatureMonitor($singleDestination, $limit));
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

    public function getCacheDuration() {
        return 1;
    }

    public function getCss() {
        return '
        * {
            padding: 0;
            margin: 0;
            font - family:' . $this->getOption('fontFamily') . ', Arial, Helvetica, sans - serif;
            font-weight: bold;
            font-size: 30px;
        }
        
        body {
            width: 100%;
            overflow: hidden;
            background-color: #ffffff;
        }
        
        #wrapper {
            width: 100%;
        }
        
        /* Tabelle */
        table {
            border-collapse: collapse;
            width: 100%;
            table-layout: fixed;
        }
        
        table thead tr th{
            padding: .5% 0;
            background: #36304a;
            color: #f5f5f5;
            font-size: 30px;
        }
        
        td {
            padding-top: .5%;
            padding-bottom: .5%; 
        }
          
        .column2 {
            text-align: right;
            padding-right: 5%;
        }
          
        .column3 {
            padding-left: 0%;
        }
          
        .column4 {
            padding-left: 0%;
        }
          
        .column5 {
            text-align: left;
            padding-right: 0%;
        }
          
        .column6 {
            text-align:right; 
            padding-right: 4%;
        }
        
        /* Icons */
        /* .station {
            width: 8%;
            margin-right: 40%;
        } */
        
        img {
            width: 27%;
            display: block;
            float: right;
            padding-right: 40%;
        }
        
        /* Responsive */
        /* 4K */
        @media only screen and (min-width: 2202px) {
            table thead tr th {
                font-size: 50px;
            }
            
            td {
                font-size: 40px;
                padding: .1% 0;
            }
        }
        
        /* Full HD */
        @media only screen and (min-width: 1921px) and (max-width: 2217px) {
            table thead tr th {
                font-size: 30px;
            }
            
            td {
                font-size: 30px;
                padding: .13% 0;
            }
        }
        
        /* medium */
        @media only screen and (min-width: 890px) and (max-width: 1135px){
            table thead tr th {
                font-size: 18px;
            }
            
            td {
                font-size: 20px;
                padding: 1% 0;
            }
        }
        
        /* small */
        @media only screen and (max-width: 890px){
            table thead tr th {
                font-size: 12px;
            }
            
            td {
                font-size: 15px;
                padding: .8% 0;
            }
            
            img {
                display: none;
            }
        
            #tbl-head1, .column1 {
                display: none;
            }
        
            #tbl-head2, .column2 {
                padding-left: 1%;
                text-align: right;
            }
        
            #tbl-head3, .column3 {
                padding-left: 3%;
                text-align: left;
            }
            .column4 {
                padding-left: 3%;
            }
        
            #tbl-head5 {
                padding-right: 5%;
                text-align: left;
            }
        
            .column5 {
                padding-right: 3%;
                text-align: left;
            }
        
            #tbl-head6 {
                padding-right: 50%;
                text-align: left;
            }
        
            .column6 {
                padding-left: 0;
                text-align: left;
            }
        }';
    }
}