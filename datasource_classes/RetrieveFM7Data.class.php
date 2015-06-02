<?php

require_once('RetrieveFMXML.class.php');

#### Part of FX.php #####################################################
#                                                                       #
#  License: Artistic License and addendum (included with release)       #
# Web Site: www.iviking.org                                             #
#                                                                       #
#########################################################################

class RetrieveFM7Data extends RetrieveFMXML {

    var $fmDataFile = 'FMPXMLRESULT.xml';
    var $xmlStartHandler = 'StartElement';
    var $xmlContentHandler = 'ElementContents';
    var $xmlEndHandler = 'EndElement';

    function CreateCurrentSort () {
        $currentSort = "";

        foreach ($this->FX->sortParams as $key1 => $value1) {
            $field = '';
            $sortOrder = ''; // prevent IDE complaint. (msyk, Feb 1, 2012)
            foreach ($value1 as $key2 => $value2) {
                $$key2 = $value2;
            }
            if ($sortOrder == '') {
                $currentSort .= "&-sortfield.{$key1}=" . str_replace ('%3A%3A', '::', rawurlencode($field));
            }
            else {
                $currentSort .= "&-sortfield.{$key1}=" . str_replace ("%3A%3A", "::", rawurlencode($field)) . "&-sortorder.{$key1}=" . $sortOrder;
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
                $tempFieldName = str_replace('%3A%3A', '::', urlencode($name));
                if ($tempFieldName == '-recid') {
                    $currentSearch .= '&' . $tempFieldName . '=' . urlencode($value);
                } else {
                    $currentSearch .= '&' . $tempFieldName . '.op=' . $op . '&' . $tempFieldName . '=' . urlencode($value);
                }
            }
        }
        return $currentSearch;
    }

    function doQuery ($action) {
        // if section added by Nick Salonen for findquery
        if ($action == '-findquery')
        {
            $queryExists = false;
            $tempcount = count($this->FX->dataParams);
            foreach($this->FX->dataParams as $key=>$row) // for each dataParam
            {
                if ($queryExists) continue;
                // check to see if the field/value pair exists in the dataParams already. if so, use it via foundFlagQNumber.
                if ($row['name'] === '-query')
                {
                    $queryExists = true;
                }
            }
            if ($queryExists)
            {
                // continue
            } else {
                if ($this->FX->findquerystring == '')
                {
                    if ((defined("DEBUG") and DEBUG) or DEBUG_FUZZY) {
                        $currentDebugString = "<p>FindQuery Finds must have a -query parameter</p>\n";
                        $this->lastDebugMessage .= $currentDebugString;
                    }
                    return new FX_Error("FindQuery Finds must have a -query parameter.");
                } else {
                    if (strpos($this->FX->findquerystring, ';') === 0)
                    {
                        $this->FX->findquerystring = substr($this->FX->findquerystring, 1);
                    }
                    // would just need this section if using all of the FX->FindQuery_Append command, etc. and no manual entering.
                    $this->FX->AddDBParam('-query', $this->FX->findquerystring);
                    // continue
                }
            }
            
        }
        $data = '';
        if (($this->FX->DBPassword != '' || $this->FX->DBUser != 'FX') && !defined('CURLOPT_HTTPAUTH')) {     // Assemble the Password Data
            $this->FX->userPass = rawurlencode($this->FX->DBUser) . ':' . rawurlencode($this->FX->DBPassword) . '@';
        }
        if ($this->FX->layout != '') {                                      // Set up the layout portion of the query.
            $layRequest = '&-lay=' . urlencode($this->FX->layout);
            if ($this->FX->responseLayout != '') {
                $layRequest .= '&-lay.response=' . urlencode($this->FX->responseLayout);
            }
        }
        else {
            $layRequest = "";
        }
        if ($this->FX->currentSkip > 0) {                                   // Set up the skip size portion of the query.
            $skipRequest = "&-skip={$this->FX->currentSkip}";
        } else {
            $skipRequest = "";
        }
        $currentSort = $this->CreateCurrentSort();
        $currentSearch = $this->CreateCurrentSearch();
        if ($action == '-view') {
            $FMFile = 'FMPXMLLAYOUT.xml';
        } else {
            $FMFile = $this->fmDataFile;
        }
        $this->dataURL = "{$this->FX->urlScheme}://{$this->FX->userPass}{$this->FX->dataServer}{$this->FX->dataPortSuffix}/fmi/xml/{$FMFile}"; // First add the server info to the URL...
        $this->dataURLParams = $this->AssembleCurrentQuery($layRequest, $skipRequest, $currentSort, $currentSearch, $action, 7);
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
                return new FX_Error("Failed to retrieve cached URL in RetrieveFM7Data()");
            }
            $data = $data["Body"];
        } elseif( $this->FX->isFOpenQuery ) {
/*
Amendment by Gjermund G Thorsen -> ggt667@me.com, this function is written to read files exported using File Export in FMSA 10 and newer
This function is particularly written for huge queries of data that are less likely to change often and that would otherwise choke FM WPE

It will also prove beneficiary for queries that are frequent, and where data is updated through publishing to file,
such as news articles, product descriptions, and similar use.

Suggested use, export as XML without XSLT to xslt folder of webserver as an example:

/var/www/com.example.www/xml/product/<<productnumber>>.xml
/var/www/com.example.www/xml/news/<<newsnumber>>.xml
/var/www/com.example.www/xml/article/<<articlenumber>>.xml

The only thing that should be left for direct communication via WPE now should be live order data,
and places where you will have to set flags in the order process.
*/
            $f = fopen( $this->FX->dataServer, 'rb' );
            $data = '';
            if( ! $f ) {
                return new FX_Error( "Failed to retrieve FOpen( '" . $this->FX->dataServer . "', 'rb' ) File not found?" );
            } else {
                while( ! feof( $f ) )
                    $data .= fread( $f, 4096 );
                fclose( $f );
            }
        } elseif ($this->FX->isPostQuery) {
            if ($this->FX->useCURL && defined("CURLOPT_TIMEVALUE")) {
                $curlHandle = curl_init(str_replace('?' . $this->dataURLParams, '', $this->dataURL));
                curl_setopt($curlHandle, CURLOPT_PORT, $this->FX->dataPort);
                curl_setopt($curlHandle, CURLOPT_HEADER, 0);
                curl_setopt($curlHandle, CURLOPT_POST, 1);
                if ($this->FX->verifyPeer == false) curl_setopt($curlHandle, CURLOPT_SSL_VERIFYPEER,false);
                curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $this->dataURLParams);
                if (($this->FX->DBPassword != '' || $this->FX->DBUser != 'FX') && defined('CURLOPT_HTTPAUTH')) {
                    curl_setopt($curlHandle, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                    curl_setopt($curlHandle, CURLOPT_USERPWD, $this->FX->DBUser . ':' . $this->FX->DBPassword);
                }
                ob_start();
                if (! curl_exec($curlHandle)) {
                    $this->FX->lastDebugMessage .= "<p>Unable to connect to FileMaker.  Use the DEBUG constant and try connecting with the resulting URL manually.<br />\n";
                    $this->FX->lastDebugMessage .= "You should also double check the user name and password used, the server address, and WPE configuration.</p>\n";
                    return new FX_Error("cURL could not retrieve Post data in RetrieveFM7Data(). A bad URL is the most likely reason.");
                }
                curl_close($curlHandle);
                $data = trim(ob_get_contents());
                ob_end_clean();
                if (substr($data, -1) != '>') {
                    $data = substr($data, 0, -1);
                }
            } else {
                $dataDelimiter = "\r\n";
                $socketData = "POST /fmi/xml/{$FMFile} HTTP/1.0{$dataDelimiter}";
                if ((defined("DEBUG") and DEBUG) or DEBUG_FUZZY) {
                    $currentDebugString = "<p>Using socket [$socketData] - FileMaker URL: <a href=\"{$this->FX->dataURL}\">{$this->dataURL}</a></p>\n";
                    $this->FX->lastDebugMessage .= $currentDebugString;
                    if (defined("DEBUG") and DEBUG) {
                        echo $currentDebugString;
                    }
                }
                if (strlen(trim($this->FX->userPass)) > 1) {
                    $socketData .= "Authorization: Basic " . base64_encode($this->FX->DBUser . ':' . $this->FX->DBPassword) . $dataDelimiter;
                }
                $socketData .= "Host: {$this->FX->dataServer}:{$this->FX->dataPort}{$dataDelimiter}";
                $socketData .= "Pragma: no-cache{$dataDelimiter}";
                $socketData .= "Content-length: " . strlen($this->dataURLParams) . $dataDelimiter;
                $socketData .= "Content-type: application/x-www-form-urlencoded{$dataDelimiter}";
                $socketData .= $dataDelimiter . $this->dataURLParams;

                // Check if SSL is required
                if ($this->FX->useSSLProtocol) {
                    $protocol = "ssl://";
                } else {
                    $protocol = "";
                }

                // debug to see what protocol is being used
                if ((defined("DEBUG") and DEBUG) or DEBUG_FUZZY) {
                    $currentDebugString = "<p>Domain and Protocol are {$protocol}{$this->FX->dataServer}</p>\n";
                    $this->FX->lastDebugMessage .= $currentDebugString;
                    if (defined("DEBUG") and DEBUG) {
                        echo $currentDebugString;
                    }
                }

                $fp = fsockopen ($protocol . $this->FX->dataServer, $this->FX->dataPort, $this->FX->errorTracking, $this->FX->fxError, 30);
                if (! $fp) {
                    $this->FX->lastDebugMessage .= "<p>Unable to connect to FileMaker.  Use the DEBUG constant and try connecting with the resulting URL manually.<br />\n";
                    $this->FX->lastDebugMessage .= "You should also double check the user name and password used, the server address, and WPE configuration.</p>\n";
                    return new FX_Error( "Could not fsockopen the URL in retrieveFM7Data" );
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
                $this->FX->lastDebugMessage .= "You should also double check the user name and password used, the server address, and WPE configuration.</p>\n";
                return new FX_Error("Could not fopen URL in RetrieveFM7Data.");
            }
            while (!feof($fp)) {
                $data .= fread($fp, 4096);
            }
            fclose($fp);
        }

        // Clean the data
        $data = str_replace($this->invalidXMLChars, '', $data);

        // Parse the XML
        $xml_parser = xml_parser_create("UTF-8");
        xml_set_object($xml_parser, $this);
        xml_set_element_handler($xml_parser, $this->xmlStartHandler, $this->xmlEndHandler);
        xml_set_character_data_handler($xml_parser, $this->xmlContentHandler);
        $xmlParseResult = xml_parse($xml_parser, $data, true);
        if (! $xmlParseResult) {
/* Masayuki Nii added at Oct 9, 2009 */
            $this->columnCounter = -1; 
            xml_parser_free($xml_parser);
            $xml_parser = xml_parser_create("UTF-8");
            xml_set_object($xml_parser, $this);
            xml_set_element_handler($xml_parser, $this->xmlStartHandler, $this->xmlEndHandler);
            xml_set_character_data_handler($xml_parser, $this->xmlContentHandler);
            $xmlParseResult = xml_parse($xml_parser, $this->ConvertSurrogatePair( $data ), true);
            if (! $xmlParseResult) {
/* ==============End of the addition */
                $theMessage = sprintf("ExecuteQuery XML error: %s at line %d",
                    xml_error_string(xml_get_error_code($xml_parser)),
                    xml_get_current_line_number($xml_parser));
                xml_parser_free($xml_parser);
                $this->FX->lastDebugMessage .= "<p>Unable to parse FileMaker XML.  Use the DEBUG constant and try connecting with the resulting URL manually.<br />\n";
                $this->FX->lastDebugMessage .= "You should also double check the <strong>user name</strong> and <strong>password</strong> used, the <strong>server address and port</strong>, and <strong>WPE configuration</strong>.<br />\n";
                $this->FX->lastDebugMessage .= "Finally, be sure that you have specified the correct <strong>data type</strong> (e.g. FileMaker 5 or 6 versus 7 or 8.)</p>\n";
                return new FX_Error($theMessage);
/* Masayuki Nii added at Oct 9, 2009 */
            }
/* ==============End of the addition */
        }
        xml_parser_free($xml_parser);
        return true;

    }

/* Convert wrong surrogated-pair character to light code sequence in UTF-8
 * Masayuki Nii (msyk@msyk.net) Oct 9, 2009, Moved here on Feb 6,2012
 * Refered http://www.nii.ac.jp/CAT-ILL/about/system/vista.html
 */
    function ConvertSurrogatePair($data) {
        $altData = '';
        for ($i=0; $i<strlen($data); $i++) {
            $c = substr( $data, $i, 1 );
            if (( ord($c) == 0xed )&&( (ord(substr( $data, $i+1, 1 )) & 0xF0) == 0xA0 )) {
                for ( $j = 0; $j < 6 ; $j++ )
                    $utfSeq[] = ord(substr($data, $i+$j,1));
                $convSeq[3] = $utfSeq[5];
                $convSeq[2] = $utfSeq[4] & 0x0F | (($utfSeq[2] & 0x03) << 4) | 0x80;
                $topDigit = ($utfSeq[1] & 0x0F) + 1;
                $convSeq[1] = (($utfSeq[2] >> 2) & 0x0F) | (($topDigit & 0x03) << 4) | 0x80;
                $convSeq[0] = (($topDigit >> 2) & 0x07) | 0xF0;
                $c = chr( $convSeq[0] ).chr( $convSeq[1] ).chr( $convSeq[2] ).chr( $convSeq[3] );
                $i += 5;
            }
            $altData .= $c;
        }
        return $altData;
    }

}

?>
