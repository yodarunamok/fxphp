<?php

require_once('RetrieveFXData.class.php');

#### Part of FX.php #####################################################
#                                                                       #
#  License: Artistic License and addendum (included with release)       #
# Web Site: www.iviking.org                                             #
#                                                                       #
#########################################################################

// Do not use this class directly -- it is designed to be appropriately extended
class RetrieveFMXML extends RetrieveFXData {

    var $currentFlag = '';
    var $currentValueList = '';
    var $currentValueListElement;
    var $currentRecord = '';
    var $currentSubrecordIndex;
    var $currentField = '';
    var $currentFieldIndex;
    var $columnCounter = -1;                                              // columnCounter is ++ed BEFORE looping
    var $dataURL = '';
    var $dataURLParams = '';
    var $invalidXMLChars = array("\x0B", "\x0C", "\x12");

    /*
        Translation arrays used with str_replace to handle special
        characters in UTF-8 data received from FileMaker. The two arrays
        should have matching numeric indexes such that $UTF8SpecialChars[0]
        contains the raw binary equivalent of $UTF8HTMLEntities[0].

        This would be a perfect use for strtr(), except that it only works
        with single-byte data. Instead, we use preg_replace, which means
        that we need to delimit our match strings

        Please note that in this latest release I've removed the need for
        the include files which contained long lists of characters. Gjermund
        was sure there was a better way and he was right. With the two six
        element arrays below, every unicode character is allowed for. Let
        me know how this works for you. A link to Gjermund's homepage can
        be found in the FX Links section of www.iViking.org.
     */
    var $UTF8SpecialChars = array(
        "|([\xC2-\xDF])([\x80-\xBF])|e",
        "|(\xE0)([\xA0-\xBF])([\x80-\xBF])|e",
        "|([\xE1-\xEF])([\x80-\xBF])([\x80-\xBF])|e",
        "|(\xF0)([\x90-\xBF])([\x80-\xBF])([\x80-\xBF])|e",
        "|([\xF1-\xF3])([\x80-\xBF])([\x80-\xBF])([\x80-\xBF])|e",
        "|(\xF4)([\x80-\x8F])([\x80-\xBF])([\x80-\xBF])|e"
    );

    var $UTF8HTMLEntities = array(
        "\$this->BuildExtendedChar('\\1','\\2')",
        "\$this->BuildExtendedChar('\\1','\\2','\\3')",
        "\$this->BuildExtendedChar('\\1','\\2','\\3')",
        "\$this->BuildExtendedChar('\\1','\\2','\\3','\\4')",
        "\$this->BuildExtendedChar('\\1','\\2','\\3','\\4')",
        "\$this->BuildExtendedChar('\\1','\\2','\\3','\\4')"
    );

    function getTOCName($fieldName) {
        $p = strpos($fieldName,'::');
        if ( $p === false ) {
            return 'ERROR-TOC name is conflicted.';
        }
        $tocName = substr($fieldName,0,$p);
        if ($this->remainNamesReverse[$tocName] !== true)   {
            return $this->remainNamesReverse[$tocName];
        }
        return $tocName;
    }

    function StartElement($parser, $name, $attrs) {                      // The functions to start XML parsing begin here
        switch(strtolower($name)) {
             case "data":
                $this->currentFlag = "parseData";
                if ($this->FX->useInnerArray) {
                    $this->FX->currentData[$this->currentRecord][$this->currentField][$this->currentFieldIndex] = "";
                } else {
                    if ($this->FX->isRemainName($this->currentField))    {
                        if ($this->FX->portalAsRecord ) {
                            $this->FX->currentData[$this->currentRecord][$this->getTOCName($this->currentField)][$this->currentSubrecordIndex][$this->currentField] = '';
                        } else {
                            $this->FX->currentData[$this->currentRecord][$this->currentField][$this->currentFieldIndex] = "";
                        }
                    } else {
                        $this->FX->currentData[$this->currentRecord][$this->currentField] = "";
                    }
                }
                break;
            case "col":
                $this->currentFieldIndex = 0;
                ++$this->columnCounter;
                $this->currentField = $this->FX->fieldInfo[$this->columnCounter]['name'];
                if ($this->FX->useInnerArray) {
                    $this->FX->currentData[$this->currentRecord][$this->currentField] = array();
                } else if ($this->FX->isRemainName($this->currentField)) {
                    if ( $this->FX->portalAsRecord ) {
                        $this->currentSubrecordIndex = 0;
                    } else {
                        $this->FX->currentData[$this->currentRecord][$this->currentField] = array();
                    }
                }
                break;
            case "row":
                $recordid = ''; // prevent IDE complaint. (msyk, Feb 1, 2012)
                foreach ($attrs as $key => $value) {
                    $key = strtolower($key);
                    $$key = $value;
                }
                if (substr_count($this->dataURL, '-dbnames') > 0 || substr_count($this->dataURL, '-layoutnames') > 0) {
                    $modid = count($this->FX->currentData);
                }
                $this->currentRecord = $recordid . '.' . $modid;
                $this->FX->currentData[$this->currentRecord] = array( '-recid' => $recordid, '-modid' => $modid );
                break;
            case "field":
                if ($this->FX->charSet  != '' && defined('MB_OVERLOAD_STRING')) {
                    foreach ($attrs as $key => $value) {
                        $key = strtolower($key);
                        $this->FX->fieldInfo[$this->FX->fieldCount][$key] = mb_convert_encoding($value, $this->FX->charSet, 'UTF-8');
                    }
                } else {
                    foreach ($attrs as $key => $value) {
                        $key = strtolower($key);
                        $this->FX->fieldInfo[$this->FX->fieldCount][$key] = $value;
                    }
                }
                $this->FX->fieldInfo[$this->FX->fieldCount]['extra'] = ''; // for compatibility w/ SQL databases
                if (substr_count($this->dataURL, '-view') < 1) {
                    $this->FX->fieldCount++;
                }
                break;
            case "style":
                foreach ($attrs as $key => $value) {
                    $key = strtolower($key);
                    $this->FX->fieldInfo[$this->FX->fieldCount][$key] = $value;
                }
                break;
            case "resultset":
                foreach ($attrs as $key => $value) {
                    switch(strtolower($key)) {
                        case "found":
                          $this->FX->foundCount = (int)$value;
                          break;
                    }
                }
                break;
            case "errorcode":
                $this->currentFlag = "fmError";
                break;
            case "valuelist":
                foreach ($attrs as $key => $value) {
                    if (strtolower($key) == "name") {
                        $this->currentValueList = $value;
                    }
                }
                $this->FX->valueLists[$this->currentValueList] = array();
                $this->currentFlag = "values";
                $this->currentValueListElement = -1;
                break;
            case "value":
                $this->currentValueListElement++;
                $this->FX->valueLists[$this->currentValueList][$this->currentValueListElement] = "";
                break;
            case "database":
                foreach ($attrs as $key => $value) {
                    switch(strtolower($key)) {
                        case "dateformat":
                          $this->FX->dateFormat = $value;
                          break;
                        case "records":
                          $this->FX->totalRecordCount = $value;
                          break;
                        case "timeformat":
                          $this->FX->timeFormat = $value;
                          break;
                    }
                }
                break;
            default:
                break;
        }
    }

    function ElementContents($parser, $data) {
        switch($this->currentFlag) {
            case "parseData":
                if ($this->FX->dataParamsEncoding  != '' && defined('MB_OVERLOAD_STRING')) {
                    if ($this->FX->useInnerArray) {
                        $this->FX->currentData[$this->currentRecord][$this->currentField][$this->currentFieldIndex] .= mb_convert_encoding($data, $this->FX->charSet, 'UTF-8');
                    } else {
                        if ($this->FX->isRemainName($this->currentField))    {
                            if ( $this->FX->portalAsRecord )    {
                                $this->FX->currentData[$this->currentRecord][$this->getTOCName($this->currentField)][$this->currentSubrecordIndex][$this->currentField] .= mb_convert_encoding($data, $this->FX->charSet, 'UTF-8');
                            } else {
                                $this->FX->currentData[$this->currentRecord][$this->currentField][$this->currentFieldIndex] .= mb_convert_encoding($data, $this->FX->charSet, 'UTF-8');
                            }
                        } else {
                            $this->FX->currentData[$this->currentRecord][$this->currentField] .= mb_convert_encoding($data, $this->FX->charSet, 'UTF-8');
                        }
                    }
                } else {
                    if ($this->FX->useInnerArray) {
                        $this->FX->currentData[$this->currentRecord][$this->currentField][$this->currentFieldIndex] .= preg_replace($this->UTF8SpecialChars, $this->UTF8HTMLEntities, $data);
                    } else {
                        if ($this->FX->isRemainName($this->currentField)) {
                            if ($this->FX->portalAsRecord) {
                                   $this->FX->currentData[$this->currentRecord][$this->getTOCName($this->currentField)][$this->currentSubrecordIndex][$this->currentField] .= preg_replace($this->UTF8SpecialChars, $this->UTF8HTMLEntities, $data);
                               } else {
                                   $this->FX->currentData[$this->currentRecord][$this->currentField][$this->currentFieldIndex] .= preg_replace($this->UTF8SpecialChars, $this->UTF8HTMLEntities, $data);
                            }
                        } else {
                            $this->FX->currentData[$this->currentRecord][$this->currentField] .= preg_replace($this->UTF8SpecialChars, $this->UTF8HTMLEntities, $data);
                        }
                    }
                }
                break;
            case "fmError":
                $this->FX->fxError = $data;
                break;
            case "values":
                $this->FX->valueLists[$this->currentValueList][$this->currentValueListElement] .= preg_replace($this->UTF8SpecialChars, $this->UTF8HTMLEntities, $data);
                break;
        }
    }

    function EndElement($parser, $name) {
        switch(strtolower($name)) {
            case "data":
                $this->currentFieldIndex++;
                $this->currentFlag = "";
                $this->currentSubrecordIndex++;
                break;
            case "col":
                break;
            case "row":
                if (strlen(trim($this->FX->customPrimaryKey)) > 0) {
                    if ($this->FX->useInnerArray) {
                        $this->FX->currentData[$this->FX->currentData[$this->currentRecord][$this->FX->customPrimaryKey][0]] 
                            = $this->FX->currentData[$this->currentRecord];
                    } else {
                        if ($this->FX->isRemainName($this->currentField)) {
                            if ($this->FX->portalAsRecord) {
                                //
                            } else {
                                $this->FX->currentData[$this->FX->currentData[$this->currentRecord][$this->FX->customPrimaryKey][0]] 
                                    = $this->FX->currentData[$this->currentRecord];
                            }
                        } else {
                            $this->FX->currentData[$this->FX->currentData[$this->currentRecord][$this->FX->customPrimaryKey]] 
                                = $this->FX->currentData[$this->currentRecord];
                        }
                    }
                    unset($this->FX->currentData[$this->currentRecord]);
                }
                $this->columnCounter = -1;
                break;
            case "field":
                if (substr_count($this->dataURL, '-view') > 0) {
                    $this->FX->fieldCount++;
                }
                break;
            case "errorcode":
            case "valuelist":
                $this->currentFlag = "";
                break;
        }
    }                                                                       // XML Parsing Functions End Here

    function AssembleCurrentQuery ($layRequest, $skipRequest, $currentSort, $currentSearch, $action, $FMV=6)
    {
        $tempSearch = '';

        $tempSearch = '-db=' . urlencode($this->FX->database);           // add the name of the database...
        $tempSearch .= $layRequest;                                      // and any layout specified...
        if ($FMV < 7) {
            $tempSearch .= '&-format=-fmp_xml';                          // then set the FileMaker XML format to use...
        }
        $tempSearch .= "&-max={$this->FX->groupSize}{$skipRequest}";     // add the set size and skip size data...
        $tempSearch .= $currentSort . $currentSearch . '&' . $action;    // finally, add sorting, search parameters, and action data.
        return $tempSearch;
    }

    // Method to clean up after retrieval
    function cleanUp() {
        // Pass through assembled data as appropriate
        $this->FX->lastURL = $this->dataURL;
        // Clear the flags and temp variables used during retrieval and parsing
        $this->currentFlag = '';
        $this->currentRecord = '';
        $this->currentField = '';
        $this->columnCounter = -1;
        $this->dataURL = '';
        $this->dataURLParams = '';
    }

}

?>