<?php

require_once('RetrieveFMXML.class.php');

#### Part of FX.php #####################################################
#                                                                       #
#  License: Artistic License and addendum (included with release)       #
# Web Site: www.iviking.org                                             #
#                                                                       #
#########################################################################

class RetrieveFM5Data extends RetrieveFMXML {


    function CreateCurrentSort () {
        $currentSort = '';

        foreach ($this->FX->sortParams as $key1 => $value1) {
            $field = '';
            $sortOrder = ''; // prevent IDE complaint. (msyk, Feb 1, 2012)
            foreach ($value1 as $key2 => $value2) {
                $$key2 = $value2;
            }
            if ($sortOrder == '') {
                $currentSort .= '&-sortfield=' . str_replace('%3A%3A', '::', rawurlencode($field));
            }
            else {
                $currentSort .= '&-sortfield=' . str_replace ('%3A%3A', '::', rawurlencode($field)) . '&-sortorder=' . $sortOrder;
            }
        }
        return $currentSort;
    }

    function CreateCurrentSearch () {
        $currentSearch = '';

        foreach ($this->FX->dataParams as $key1 => $value1) {
            $name = '';
            $value = '';
            $op = ''; // prevent IDE complaint. (msyk, Feb 1, 2012)
            foreach ($value1 as $key2 => $value2) {
                $$key2 = $value2;
            }
            if ($op == '' && $this->FX->defaultOperator == 'bw') {
                $currentSearch .= '&' . str_replace ('%3A%3A', '::', urlencode($name)) . '=' . urlencode($value);
            } else {
                if ($op == '') {
                    $op = $this->FX->defaultOperator;
                }
                $currentSearch .= '&-op=' . $op . '&' . str_replace('%3A%3A', '::', urlencode($name)) . '=' . urlencode($value);
            }
        }
        return $currentSearch;
    }

    function doQuery ($action) {
        $data = '';
        if ($this->FX->DBPassword != '') {                                      // Assemble the Password Data
            $this->FX->userPass = rawurlencode($this->FX->DBUser) . ':' . rawurlencode($this->FX->DBPassword) . '@';
        }
        if ($this->FX->layout != '') {                                          // Set up the layout portion of the query.
            $layRequest = '&-lay=' . urlencode($this->FX->layout);
        }
        else {
            $layRequest = '';
        }
        if ($this->FX->currentSkip > 0) {                                       // Set up the skip size portion of the query.
            $skipRequest = '&-skip=' . $this->FX->currentSkip;
        } else {
            $skipRequest = '';
        }
        $currentSort = $this->CreateCurrentSort();
        $currentSearch = $this->CreateCurrentSearch();
        $this->dataURL = "http://{$this->FX->userPass}{$this->FX->dataServer}{$this->FX->dataPortSuffix}/FMPro"; // First add the server info to the URL...
        $this->dataURLParams = $this->AssembleCurrentQuery($layRequest, $skipRequest, $currentSort, $currentSearch, $action);
        $this->dataURL .= '?' . $this->dataURLParams;

        if ((defined("DEBUG") and DEBUG) or DEBUG_FUZZY) {
            $currentDebugString = "<p>Using FileMaker URL: <a href=\"{$this->dataURL}\">{$this->dataURL}</a></p>\n";
            $this->FX->lastDebugMessage .= $currentDebugString;
            if (defined("DEBUG") and DEBUG) {
                echo $currentDebugString;
            }
        }

        if (defined("HAS_PHPCACHE") and defined("FX_USE_PHPCACHE") and strlen($this->dataURLParams) <= 510 and (substr_count($this->dataURLParams, '-find') > 0 || substr_count($this->dataURLParams, '-view') > 0 || substr_count($this->dataURLParams, '-dbnames') > 0 || substr_count($this->dataURLParams, '-layoutnames') > 0)) {
            $data = get_url_cached($this->dataURL);
            if (! $data) {
                return new FX_Error("Failed to retrieve cached URL in RetrieveFMData()");
            }
            $data = $data["Body"];
        } elseif ($this->FX->isPostQuery) {
            if ($this->FX->useCURL && defined("CURLOPT_TIMEVALUE")) {
                $curlHandle = curl_init(str_replace($this->dataURLParams, '', $this->dataURL));
                curl_setopt($curlHandle, CURLOPT_POST, 1);
                curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $this->dataURLParams);
                ob_start();
                if (! curl_exec($curlHandle)) {
                    $this->FX->lastDebugMessage .= "<p>Unable to connect to FileMaker.  Use the DEBUG constant and try connecting with the resulting URL manually.<br />\n";
                    $this->FX->lastDebugMessage .= "You should also double check the user name and password used, the server address, and Web Companion configuration.</p>\n";
                    return new FX_Error("cURL could not retrieve Post data in RetrieveFMData(). A bad URL is the most likely reason.");
                }
                curl_close($curlHandle);
                $data = trim(ob_get_contents());
                ob_end_clean();
                if (substr($data, -1) != '>') {
                    $data = substr($data, 0, -1);
                }
            } else {
                $dataDelimiter = "\r\n";
                $socketData = "POST /FMPro HTTP/1.0{$dataDelimiter}";
                if (strlen(trim($this->FX->userPass)) > 1) {
                    $socketData .= "Authorization: Basic " . base64_encode($this->FX->DBUser . ':' . $this->FX->DBPassword) . $dataDelimiter;
                }
                $socketData .= "Host: {$this->FX->dataServer}:{$this->FX->dataPort}{$dataDelimiter}";
                $socketData .= "Pragma: no-cache{$dataDelimiter}";
                $socketData .= "Content-length: " . strlen($this->dataURLParams) . $dataDelimiter;
                $socketData .= "Content-type: application/x-www-form-urlencoded{$dataDelimiter}";
                // $socketData .= "Connection: close{$dataDelimiter}";
                $socketData .= $dataDelimiter . $this->dataURLParams;

                $fp = fsockopen ($this->FX->dataServer, $this->FX->dataPort, $this->FX->errorTracking, $this->FX->fxError, 30);
                if (! $fp) {
                    $this->FX->lastDebugMessage .= "<p>Unable to connect to FileMaker.  Use the DEBUG constant and try connecting with the resulting URL manually.<br />\n";
                    $this->FX->lastDebugMessage .= "You should also double check the user name and password used, the server address, and Web Companion configuration.</p>\n";
                    return new FX_Error( "Could not fsockopen the URL in retrieveFMData" );
                }
                fputs ($fp, $socketData);
                while (!feof($fp)) {
                    $data .= fgets($fp, 128);
                }
                fclose($fp);
                $pos = strpos($data, chr(13) . chr(10) . chr(13) . chr(10)); // the separation code
                $data = substr($data, $pos + 4) . "\r\n";
            }
        } else {
            $fp = fopen($this->dataURL, "r");
            if (! $fp) {
                $this->FX->lastDebugMessage .= "<p>Unable to connect to FileMaker.  Use the DEBUG constant and try connecting with the resulting URL manually.<br />\n";
                $this->FX->lastDebugMessage .= "You should also double check the user name and password used, the server address, and Web Companion configuration.</p>\n";
                return new FX_Error("Could not fopen URL in RetrieveFMData.");
            }
            while (!feof($fp)) {
                $data .= fread($fp, 4096);
            }
            fclose($fp);
        }
        // Clean data
        $data = str_replace($this->invalidXMLChars, '', $data);

        // Parse the XML
        $xml_parser = xml_parser_create("UTF-8");
        xml_set_object($xml_parser, $this);
        xml_set_element_handler($xml_parser, "StartElement", "EndElement");
        xml_set_character_data_handler($xml_parser, "ElementContents");
        $xmlParseResult = xml_parse($xml_parser, $data, true);
        if (! $xmlParseResult) {
            $theMessage = sprintf("ExecuteQuery XML error: %s at line %d",
                xml_error_string(xml_get_error_code($xml_parser)),
                xml_get_current_line_number($xml_parser));
            xml_parser_free($xml_parser);
            $this->FX->lastDebugMessage .= "<p>Unable to parse FileMaker XML.  Use the DEBUG constant and try connecting with the resulting URL manually.<br />\n";
            $this->FX->lastDebugMessage .= "You should also double check the <strong>user name</strong> and <strong>password</strong> used, the <strong>server address and port</strong>, and <strong>Web Companion configuration</strong>.<br />\n";
            $this->FX->lastDebugMessage .= "Finally, be sure that you have specified the correct <strong>data type</strong> (e.g. FileMaker 5 or 6 versus 7 or 8.)</p>\n";
            return new FX_Error($theMessage);
        }
        xml_parser_free($xml_parser);
        return true;

    }

}

?>