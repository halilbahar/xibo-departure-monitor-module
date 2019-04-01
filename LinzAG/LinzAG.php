<?php

namespace Xibo\Custom\LinzAG;

use Xibo\Widget\ModuleWidget;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;


class LinzAG extends ModuleWidget {


    public function installOrUpdate($moduleFactory) {
        if ($this->module == null) {
            // Install
            $module = $moduleFactory->createEmpty();
            $module->name = 'LinzAG';
            $module->type = 'linzag';
            $module->class = 'Xibo\Custom\LinzAG\LinzAG';
            $module->description = 'A module for displaying LinzAG times.';
            $module->imageUri = 'forms/library.gif';
            $module->enabled = 1;
            $module->previewEnabled = 1;
            $module->assignable = 1;
            $module->regionSpecific = 1;
            $module->renderAs = 'html';
            $module->schemaVersion = $this->codeSchemaVersion;
            $module->defaultDuration = 60;
            $module->settings = [];
            $module->viewPath = '../custom/LinzAG';

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
        return 'linzag-designer-javascript';
    }

    public function getResource($displayId = 0) {
        $destinations = explode(";", $this->getOption('destination'));
        $depatureList = array();
        $limit = $this->getOption('limit');

        foreach ($destinations as $singleDestination) {
            $depatureList = array_merge($depatureList, $this->getDepatureMonitor($singleDestination, $limit));
        }

        usort($depatureList, function ($a, $b) { //Sort the array using a user defined function
            return $a->countdown < $b->countdown ? -1 : 1; //Compare the scores
        });

        $tbody = '<tbody>';
        $tram = $this->getResourceUrl('bim.png');
        $bus = $this->getResourceUrl('bus.png');

        for ($i = 0; $i < $limit; $i++) {
            if (isset($depatureList[$i])) {
                $servingLine = $depatureList[$i]->servingLine;
                $dateTime = $depatureList[$i]->dateTime;
                $tbody .= '
            <tr>
              <td class="column1"><img src="' . ($servingLine->name == "Straßenbahn" ? $tram : $bus) . '"></td>
              <td class="column2">' . $servingLine->number . '</td>
              <td class="column3">' . $depatureList[$i]->nameWO . '</td>
              <td class="column4">' . $servingLine->direction . '</td>
              <td class="column5">' . sprintf('%02d', $dateTime->hour) . ':' . sprintf('%02d', $dateTime->minute) . '</td>
              <td class="column6">' . $depatureList[$i]->countdown . '</td>
             </tr>';
            }
        }
        $tbody .= '</tbody>';

        // Start building the template
        $this
            ->initialiseGetResource()
            ->appendViewPortWidth($this->region->width)
            ->appendJavaScriptFile('vendor/jquery-1.11.1.min.js')
            ->appendFontCss()
            ->appendCss($this->getCss())
            ->appendBody("<div id='wrapper'>
                    <table id='traffic-schedule'>
                        <thead>
                            <tr>
                                <th id='tbl-head1'></th>
                                <th id='tbl-head2' style='text-align:right;'>Linie</th>
                                <th id='tbl-head3' style='text-align:left; padding-left: 3%;'>Von</th>
                                <th id='tbl-head4' style='text-align:left; padding-left: 10%;'>Nach</th>
                                <th id='tbl-head5' style='text-align:right;'>Ab</th>
                                <th id='tbl-head6' style='text-align:right; padding-right: 4%;'>verbleibend</th>
                            </tr>
                        </thead>" . $tbody . "
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

    public function getDepatureMonitor($destination, $limit) {
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

    public function getCacheDuration(){
        return 60;
    }

    public function getCss() {
        return '* {
                padding: 0;
                margin: 0;
                font-family:' . $this->getOption('fontFamily') . ', Arial, Helvetica, sans-serif;
                font-weight: bold;
                font-size: 25px;
            }

            body {
                background-color: white;
                width: 100%;
                overflow: hidden;
            }

            #wrapper {
                width: 100%;
            }

            /* Tabelle */
            table {
                border-collapse: collapse;
                width: 100%;
            }

            table thead tr th{
                padding: .5% 0;
                background: #36304a;
                color: #f5f5f5;
            }

            tbody tr:nth-child(even) {
                background-color: #f5f5f5;
            }

            td {
                padding-left: 1%;
            }

            .column1 {
                width: 10%;
                padding-top: .2%;
                padding-left: 4%;
            }

            .column2 {
                text-align: right;
            }

            .column3 {
                padding-left: 3%;
            }

            .column4 {
                padding-left: 10%;
            }

            .column5 {
                text-align: right;
            }

            .column6 {
                text-align:right;
                padding-right: 4%;
            }

            img {
                width: 62%;
            }

            /* Responsive */
            /* 4K */
            @media only screen and (min-width: 2202px) {
                th {
                    font-size: 50px;
                }

                td {
                    font-size: 50px;
                    padding: .8% 0;
                }
            }

            /* Full HD */
            @media only screen and (min-width: 1921px) and (max-width: 2217px) {
                th {
                    font-size: 30px;
                }

                td {
                    font-size: 35px;
                    padding: .8% 0;
                }
            }

            /* medium */
            @media only screen and (min-width: 890px) and (max-width: 1135px){
                th {
                    font-size: 18px;
                }

                td {
                    font-size: 20px;
                    padding: 1% 0;
                }
            }

            /* small */
            @media only screen and (max-width: 890px){
                th {
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
            }
            ';
    }
}