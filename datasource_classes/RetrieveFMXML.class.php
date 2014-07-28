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
        Translation array and callback function used with preg_replace_callback
        to handle special characters in UTF-8 data received from FileMaker.

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
        "|([\xC2-\xDF])([\x80-\xBF])|",
        "|(\xE0)([\xA0-\xBF])([\x80-\xBF])|",
        "|([\xE1-\xEF])([\x80-\xBF])([\x80-\xBF])|",
        "|(\xF0)([\x90-\xBF])([\x80-\xBF])([\x80-\xBF])|",
        "|([\xF1-\xF3])([\x80-\xBF])([\x80-\xBF])([\x80-\xBF])|",
        "|(\xF4)([\x80-\x8F])([\x80-\xBF])([\x80-\xBF])|"
    );

	function utf8HTMLEntities($matches) {
		if(count($matches) == 2){
			return $this->FX->BuildExtendedChar($matches[0],$matches[1]);
		}elseif(count($matches) == 3){
			return $this->FX->BuildExtendedChar($matches[0],$matches[1],$matches[2]);
		}elseif(count($matches) == 4){
			return $this->FX->BuildExtendedChar($matches[0],$matches[1],$matches[2],$matches[3]);
		}
        return ''; // TODO something went wrong, but this should be explicit someday
	}

    function getTOCName($fieldName) {
        $p = strpos($fieldName,'::');
        if ( $p === false ) {
            return 'ERROR-TOC name is conflicted.';
        }
        $tocName = substr($fieldName,0,$p);
        if ($this->FX->remainNamesReverse[$tocName] !== true)   {
            return $this->FX->remainNamesReverse[$tocName];
        }
        return $tocName;
    }

    // Added by Masayuki Nii(nii@msyk.net) Dec 18, 2010, Move to here Feb 6, 2012
    function isRemainName($fieldName) {
        foreach($this->FX->remainNames as $fName) {
            if (strpos($fieldName,$fName) === 0) {
                return true;
            }
        }
        return false;
    }

    function StartElement($parser, $name, $attrs) {                      // The functions to start XML parsing begin here
        switch(strtolower($name)) {
             case "data":
                $this->currentFlag = "parseData";
                if ($this->FX->useInnerArray) {
                    $this->FX->currentData[$this->currentRecord][$this->currentField][$this->currentFieldIndex] = "";
                } else {
                    if ($this->isRemainName($this->currentField))    {
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
                } else if ($this->isRemainName($this->currentField)) {
                    if ( $this->FX->portalAsRecord ) {
                        $this->currentSubrecordIndex = 0;
                    } else {
                        $this->FX->currentData[$this->currentRecord][$this->currentField] = array();
                    }
                }
                break;
            case "row":
                $recordid = ''; // prevent IDE complaint. (msyk, Feb 1, 2012)
                $modid = 0;
                foreach ($attrs as $key => $value) {
                    $key = strtolower($key);
                    $$key = $value;
                }
                if (substr_count($this->dataURL, '-dbnames') > 0 || substr_count($this->dataURL, '-layoutnames') > 0) {
                    $modid = count($this->FX->currentData);
                }
                $this->currentRecord = $recordid . '.' . $modid;
                $this->FX->currentData[$this->currentRecord] = ! $this->FX->portalAsRecord ? array() :
                    array( '-recid' => $recordid, '-modid' => $modid );
                break;
            case "field":
                if ($this->FX->charSet != '' && function_exists('mb_convert_encoding')) {
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
                if ($this->FX->dataParamsEncoding != '' && function_exists('mb_convert_encoding')) {
                    if ($this->FX->useInnerArray) {
                        $this->FX->currentData[$this->currentRecord][$this->currentField][$this->currentFieldIndex] .= mb_convert_encoding($data, $this->FX->charSet, 'UTF-8');
                    } else {
                        if ($this->isRemainName($this->currentField))    {
                            if ( $this->FX->portalAsRecord ) {
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
                        $this->FX->currentData[$this->currentRecord][$this->currentField][$this->currentFieldIndex] .= preg_replace_callback($this->UTF8SpecialChars, array($this, 'utf8HTMLEntities'), $data);
                    } else {
                        if ($this->isRemainName($this->currentField)) {
                            if ($this->FX->portalAsRecord) {
                                   $this->FX->currentData[$this->currentRecord][$this->getTOCName($this->currentField)][$this->currentSubrecordIndex][$this->currentField] .= preg_replace_callback($this->UTF8SpecialChars, array($this, 'utf8HTMLEntities'), $data);
                               } else {
                                   $this->FX->currentData[$this->currentRecord][$this->currentField][$this->currentFieldIndex] .= preg_replace_callback($this->UTF8SpecialChars, array($this, 'utf8HTMLEntities'), $data);
                            }
                        } else {
                            $this->FX->currentData[$this->currentRecord][$this->currentField] .= preg_replace_callback($this->UTF8SpecialChars, array($this, 'utf8HTMLEntities'), $data);
                        }
                    }
                }
                break;
            case "fmError":
                $this->FX->fxError = $data;
                break;
            case "values":
                if ($this->FX->charSet != '' && function_exists('mb_convert_encoding')) {
                    $this->FX->valueLists[$this->currentValueList][$this->currentValueListElement] .= mb_convert_encoding($data, $this->FX->charSet, 'UTF-8');
                }
                // Modified by Masayuki Nii informed from Naoki Hori, July 24, 2012. To avoid the multi-byte character corruptions.
                // Modified by Masayuki Nii, Sept 11, 2012. Abobe code is just applied when the setCharacterEncoding('UTF-8') is written.
                //  The below code is applied in case of the default status and it doesn't require the mb_string module.
                else {
                    $this->FX->valueLists[$this->currentValueList][$this->currentValueListElement] .= preg_replace_callback($this->UTF8SpecialChars, array($this, 'utf8HTMLEntities'), $data);
                }
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
                        if ($this->isRemainName($this->currentField)) {
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

        if ($action != '-dbnames') {
            $tempSearch = '-db=' . urlencode($this->FX->database);       // add the name of the database...
        }
        $tempSearch .= $layRequest;                                      // and any layout specified...
        if ($FMV < 7) {
            $tempSearch .= '&-format=-fmp_xml';                          // then set the FileMaker XML format to use...
        }
        if (!in_array($action, array('-dbnames', '-layoutnames', '-scriptnames'))) {
            $tempSearch .= "&-max={$this->FX->groupSize}{$skipRequest}"; // add the set size and skip size data...
        }
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
