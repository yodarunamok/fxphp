<?php

require_once('RetrieveFXSQLData.class.php');

#### Part of FX.php #####################################################
#                                                                       #
#  License: Artistic License and addendum (included with release)       #
# Web Site: www.iviking.org                                             #
#                                                                       #
#########################################################################

class RetrieveCafePHP4pcData extends RetrieveFXSQLData {

    function doQuery ($action) {
        // Note that because of the way in which CAFEphp and FileMaker are implemented, CAFEphp must be running on the same
        // machine that is serving as the web server.  (You'll note that PHP creates a COM object which looks for a locally
        // running application.)  For this same reason, the server IP and port are irrelevant.
        $availableActions = array('-delete', '-edit', '-find', '-findall', '-new', '-sqlquery');

        if (! in_array(strtolower($action), $availableActions)) { // first off, toss out any requests for actions NOT supported under CAFEphp
            return new FX_Error("The action requested ({$action}) is not supported in CAFEphp.");
        }
        $CAFEphp_res = new COM('CAFEphp.Application'); // although username and password are optional for this function, FX.php expects them to be set
        if ($CAFEphp_res == false) {
            return new FX_Error('Unable to load to CAFEphp.');
        }
        if ((defined("DEBUG") and DEBUG) or DEBUG_FUZZY) {
            $currentDebugString = "<p>CAFEphp version: " . $CAFEphp_res->Version() . "</p>\n";
            $this->FX->lastDebugMessage .= $currentDebugString;
            if (defined("DEBUG") and DEBUG) {
                echo $currentDebugString;
            }
        }
        $theResult = $CAFEphp_res->Connect($this->FX->database, $this->FX->DBUser, $this->FX->DBPassword);
        if ($theResult != 0) {
            $CAFEphp_res->EndConnection();
            switch ($theResult) {
                case -1:
                    return new FX_Error('Unable to connect.  Be sure the FileMaker database and CAFEphp are running.');
                    break;
                case -2:
                    return new FX_Error('Certificate not present.  You MUST have a certificate.');
                    break;
                case -3:
                    return new FX_Error('Certificate is corrupt.');
                    break;
                case -4:
                    return new FX_Error('CAFEphp is not running or the demo version has expired.');
                    break;
                case -5:
                    return new FX_Error('The current demo of CAFEphp has expired.');
                    break;
                default:
                    return new FX_Error('An unknown error has occured while attempting to create the COM object.');
                    break;
            }
        }
        switch ($action) {
            case '-delete':
            case '-edit':
            case '-find':
            case '-findall':
            case '-new':
                $this->FX->dataQuery = $this->BuildSQLQuery($action);
                if (FX::isError($this->FX->dataQuery)) {
                    return $this->FX->dataQuery;
                }
            case '-sqlquery': // note that there is no preceding break, as we don't want to build a query
                if (substr(trim($this->FX->dataQuery), 0, 6) == 'SELECT') {
                    $currentSelect = true;
                    $theResult = $CAFEphp_res->Query($this->FX->dataQuery, $this->FX->groupSize);
                } else {
                    $currentSelect = false;
                    $theResult = $CAFEphp_res->Execute($this->FX->dataQuery);
                }
                if ($theResult < 0) {
                    $CAFEphp_res->EndConnection();
                    switch ($theResult) {
                        case -1:
                            return new FX_Error('No CAFEphp connection for the query.');
                            break;
                        default:
                            return new FX_Error('An unknown error occured during the query.');
                            break;
                    }
                }
                $this->FX->foundCount = $theResult;
                $theResult = $CAFEphp_res->FieldCount();
                if ($theResult < 0) {
                    $CAFEphp_res->EndConnection();
                    switch ($theResult) {
                        case -1:
                            return new FX_Error('No CAFEphp connection for the field count.');
                            break;
                        case -2:
                            return new FX_Error('No query was performed for a field count.');
                            break;
                        default:
                            return new FX_Error('An unknown error occured during the query.');
                            break;
                    }
                } else {
                    $currentFieldCount = $theResult;
                }
                for ($i = 0; $i < $currentFieldCount; ++$i) {
                    $theResult = $CAFEphp_res->FieldName($i);
                    if ($theResult == '$-CAFEphpNOCONNECTION') {
                        $CAFEphp_res->EndConnection();
                        return new FX_Error("No CAFEphp connection while retieving the name of field {$i}.");
                    } elseif ($theResult == '$-CAFEphpNOQUERY') {
                        $CAFEphp_res->EndConnection();
                        return new FX_Error("CAFEphp returned a \"No Query\" error while retieving the name of field {$i}.");
                    } elseif ($theResult == '$-CAFEphpUNKNOWNERROR') {
                        $CAFEphp_res->EndConnection();
                        return new FX_Error("CAFEphp returned an unknown error while retieving the name of field {$i}.");
                    }
                    $this->FX->fieldInfo[$i]['name'] = $theResult;
                    $this->FX->fieldInfo[$i]['type'] = 'NO DATA';
                    $this->FX->fieldInfo[$i]['emptyok'] = 'NO DATA';
                    $this->FX->fieldInfo[$i]['maxrepeat'] = 'NO DATA';
                    $this->FX->fieldInfo[$i]['extra'] = '';
                }
                if ($currentSelect) {
                    $tempRow = array();
                    for ($i = 0; $i < $this->FX->foundCount; ++$i) {
                        for ($j = 0; $j < $currentFieldCount; ++$j) {
                            $theResult = $CAFEphp_res->FieldValue($j);
                            if ($theResult == '$-CAFEphpNOCONNECTION') {
                                $CAFEphp_res->EndConnection();
                                return new FX_Error("No CAFEphp connection while retieving the value of field {$i} for record {$j}.");
                            } elseif ($theResult == '$-CAFEphpNOQUERY') {
                                $CAFEphp_res->EndConnection();
                                return new FX_Error("CAFEphp returned a \"No Query\" error while retieving the value of field {$i} for record {$j}.");
                            } elseif ($theResult == '$-CAFEphpUNKNOWNERROR') {
                                $CAFEphp_res->EndConnection();
                                return new FX_Error("CAFEphp returned an unknown error while retieving the value of field {$i} for record {$j}.");
                            }
                            if (! $this->FX->useInnerArray) {
                                $tempRow[$this->FX->fieldInfo[$j]['name']] = $theResult;
                            } else {
                                $tempRow[$this->FX->fieldInfo[$j]['name']] = array($theResult);
                            }
                            if ($this->FX->fieldInfo[$j]['name'] == $this->FX->primaryKeyField) {
                                $currentKey = $value;
                            }
                        }
                        if ($this->FX->genericKeys || $this->FX->primaryKeyField == '') {
                            $this->FX->currentData[] = $tempRow;
                        } else {
                            $this->FX->currentData[$currentKey] = $tempRow;
                        }
                        $theResult = $CAFEphp_res->MoveNext();
                        if ($theResult < 0) {
                            $CAFEphp_res->EndConnection();
                            $next = $i + 1;
                            switch ($theResult) {
                                case -1:
                                    return new FX_Error('No CAFEphp connection while moving from record {$i} to {$next}.');
                                    break;
                                case -2:
                                    return new FX_Error('There was no current query while moving from record {$i} to {$next}.');
                                    break;
                                default:
                                    return new FX_Error('An unknown error occured while moving from record {$i} to {$next}.');
                                    break;
                            }
                        }
                    }
                }
                break;
            default:
                return new FX_Error("The action requested ({$action}) is not supported in CAFEphp.");
                break;
        }
        $this->FX->fxError = 0;
        return true;
    }

}

?>