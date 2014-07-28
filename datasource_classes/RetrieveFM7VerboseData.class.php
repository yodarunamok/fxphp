<?php

require_once('RetrieveFM7Data.class.php');

#### Part of FX.php #####################################################
#                                                                       #
#  License: Artistic License and addendum (included with release)       #
# Web Site: www.iviking.org                                             #
#                                                                       #
#########################################################################

class RetrieveFM7VerboseData extends RetrieveFM7Data {

    var $currentFlag = '';
    var $currentRecord = '';
    var $currentSubrecordIndex;
    var $currentField = '';
    var $currentFieldIndex;
    var $isInRelatedSet = false;
    var $relatedSetTOC = '';

    // these values overwrite those in the parent class
    var $fmDataFile = 'fmresultset.xml';
    var $xmlStartHandler = 'elementStartVerbose';
    var $xmlContentHandler = 'elementContentsVerbose';
    var $xmlEndHandler = 'elementEndVerbose';

    /*
     * The functions for new XML parsing begin here
     */
    function elementStartVerbose ($parser, $name, $attrs) {
        switch(strtolower($name)) {
            case 'data':
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
            case 'field':
                if ($this->isInRelatedSet) {
                    $this->currentFieldIndex = $this->currentSubrecordIndex;
                }
                else $this->currentFieldIndex = 0;
                $this->currentField = $attrs['NAME'];
                if ($this->FX->useInnerArray && !$this->isInRelatedSet) {
                    $this->FX->currentData[$this->currentRecord][$this->currentField] = array();
                } else if ($this->isRemainName($this->currentField) && !$this->FX->portalAsRecord) {
                    $this->FX->currentData[$this->currentRecord][$this->currentField] = array();
                }
                break;
            case 'record':
                $recordID = $attrs['RECORD-ID'];
                if (substr_count($this->dataURL, '-dbnames') > 0) {
                    $modID = count($this->FX->currentData);
                }
                else {
                    $modID = $attrs['MOD-ID'];
                }
                if ($this->isInRelatedSet) {
                    if ($this->FX->usePortalIDs) $this->currentSubrecordIndex = $recordID . '.' . $modID;
                    if ($this->FX->portalAsRecord) {
                        $this->FX->currentData[$this->currentRecord] = array( '-recid' => $recordID, '-modid' => $modID );
                    }
                }
                else {
                    $this->currentRecord = $recordID . '.' . $modID;
                    $this->FX->currentData[$this->currentRecord] = array();
                }
                break;
            case 'relatedset':
                if ($attrs['COUNT'] > 0) {
                    $this->isInRelatedSet = true;
                    $this->currentSubrecordIndex = 0;
                    $this->relatedSetTOC = $attrs['TABLE'];
                }
                break;
            case 'datasource':
                // TODO: Do we want to allow use of the additional data provided here now?
                $this->FX->dateFormat = $attrs['DATE-FORMAT'];
                $this->FX->timeFormat = $attrs['TIME-FORMAT'];
                $this->FX->totalRecordCount = $attrs['TOTAL-COUNT'];
                break;
            case 'field-definition':
                if ($this->FX->charSet  != '' && function_exists('mb_convert_encoding')) {
                    $this->FX->fieldInfo[$this->FX->fieldCount]['name'] = mb_convert_encoding($attrs['NAME'], $this->FX->charSet, 'UTF-8');
                }
                else {
                    $this->FX->fieldInfo[$this->FX->fieldCount]['name'] = $attrs['NAME'];
                }
                $this->FX->fieldInfo[$this->FX->fieldCount]['emptyok'] = (($attrs['NOT-EMPTY'] == 'yes')?'no':'yes');
                $this->FX->fieldInfo[$this->FX->fieldCount]['maxrepeat'] = $attrs['MAX-REPEAT'];
                $this->FX->fieldInfo[$this->FX->fieldCount]['type'] = $attrs['RESULT'];
                $this->FX->fieldInfo[$this->FX->fieldCount]['extra'] = ''; // for compatibility w/ SQL databases
                if (substr_count($this->dataURL, '-view') < 1) {
                    $this->FX->fieldCount++;
                }
                break;
            case 'resultset':
                $this->FX->foundCount = (int)$attrs['COUNT'];
                break;
            case 'error':
                $this->FX->fxError = $attrs['CODE'];
                break;
            case 'product':
                break;
            default:
                break;
        }
    }

    function elementContentsVerbose($parser, $data) {
        switch($this->currentFlag) {
            case 'parseData':
                if ($this->FX->useInnerArray) {
                    $this->FX->currentData[$this->currentRecord][$this->currentField][$this->currentFieldIndex] .= $this->xmlDecode($data);
                } else {
                    if ($this->isRemainName($this->currentField))    {
                        if ( $this->FX->portalAsRecord ) {
                            $this->FX->currentData[$this->currentRecord][$this->relatedSetTOC][$this->currentSubrecordIndex][$this->currentField] .= $this->xmlDecode($data);
                        } else {
                            $this->FX->currentData[$this->currentRecord][$this->currentField][$this->currentFieldIndex] .= $this->xmlDecode($data);
                        }
                    } else {
                        $this->FX->currentData[$this->currentRecord][$this->currentField] .= $this->xmlDecode($data);
                    }
                }
                break;
            default:
                break;
        }
    }

    function elementEndVerbose($parser, $name) {
        switch(strtolower($name)) {
            case 'data':
                if (!$this->isInRelatedSet) ++$this->currentFieldIndex;
                $this->currentFlag = "";
                break;
            case 'field':
                break;
            case 'record':
                if ($this->isInRelatedSet && !$this->FX->usePortalIDs) ++$this->currentSubrecordIndex;
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
                elseif (!$this->isInRelatedSet) {
                    // add any missing fields as blank data
                    foreach ($this->FX->fieldInfo as $tempField) {
                        if (!isset($this->FX->currentData[$this->currentRecord][$tempField['name']])) {
                            if ($this->FX->useInnerArray) {
                                $this->FX->currentData[$this->currentRecord][$tempField['name']] = array();
                            }
                            else {
                                $this->FX->currentData[$this->currentRecord][$tempField['name']] = '';
                            }
                        }
                    }
                }
                break;
            case 'relatedset':
                $this->isInRelatedSet = false;
                break;
            default:
                break;
        }
    }
    /*
     * The functions for new XML parsing end here
     */

    function xmlDecode ($value) {
        if ($this->FX->dataParamsEncoding != '' && function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($value, $this->FX->charSet, 'UTF-8');
        }
        return preg_replace_callback($this->UTF8SpecialChars, array($this, 'utf8HTMLEntities'), $value);
    }

    
}
