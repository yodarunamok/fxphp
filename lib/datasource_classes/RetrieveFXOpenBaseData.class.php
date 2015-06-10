<?php

require_once('RetrieveFXSQLData.class.php');

#### Part of FX.php #####################################################
#                                                                       #
#  License: Artistic License and addendum (included with release)       #
# Web Site: www.iviking.org                                             #
#                                                                       #
#########################################################################

class RetrieveFXOpenBaseData extends RetrieveFXSQLData {

    function doQuery ($action) {
        $availableActions = array('-delete', '-edit', '-find', '-findall', '-new', '-sqlquery');
        $columnTypes = array( 1 => 'char', 2 => 'integer', 3 => 'float', 4 => 'long', 5 => 'money', 6 => 'date', 7 => 'time', 8 => 'object', 9 => 'datetime', 10 => 'longlong', 11 => 'boolean', 12 => 'binary', 13 => 'text', 14 => 'timestamp');

        if (! in_array(strtolower($action), $availableActions)) { // first off, toss out any requests for actions NOT supported under OpenBase
            return new FX_Error("The action requested ({$action}) is not supported by OpenBase via FX.php.");
        }
        // although username and password are optional for this function, FX.php expects them to be set
        $openBase_res = ob_connect($this->FX->database, $this->FX->dataServer, $this->FX->DBUser, $this->FX->DBPassword);
        if (substr(trim($openBase_res), 0, 13) != 'Resource id #') {
            return new FX_Error("Error {$theResult}.  Unable to connect to OpenBase database.");
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
                ob_makeCommand($openBase_res, $this->FX->dataQuery);
                $theResult = ob_executeCommand($openBase_res);
                if (! $theResult) {
                    $tempErrorText = ob_servermessage($openBase_res);
                    ob_disconnect($openBase_res); // ob_disconnect() is not in the documentation
                    return new FX_Error("Unsuccessful query: {$this->FX->dataQuery} ({$tempErrorText})");
                }
                $fieldCount = ob_resultColumnCount($openBase_res);
                for ($i = 0; $i < $fieldCount; ++$i) {
                    $this->FX->fieldInfo[$i]['name'] = ob_resultColumnName($openBase_res, $i);
                    $this->FX->fieldInfo[$i]['type'] = ob_resultColumnType($openBase_res, $i);
                    $this->FX->fieldInfo[$i]['emptyok'] = 'NO DATA';
                    $this->FX->fieldInfo[$i]['maxrepeat'] = 1;
                    $this->FX->fieldInfo[$i]['extra'] = '';
                }
                $this->FX->foundCount = ob_rowsAffected($openBase_res);
                $retrieveRow = array();
                $currentKey = '';
                while (ob_resultReturned($openBase_res) && ob_nextRowWithArray($openBase_res, $retrieveRow)) {
                    $tempRow = array();
                    foreach ($retrieveRow as $key => $value) {
                        if (! $this->FX->useInnerArray) {
                            $tempRow[$this->FX->fieldInfo[$key]['name']] = $value;
                        } else {
                            $tempRow[$this->FX->fieldInfo[$key]['name']] = array($value);
                        }
                        if ($key == $this->FX->primaryKeyField) {
                            $currentKey = $value;
                        } elseif ($this->FX->primaryKeyField == '' && $this->FX->fieldInfo[$key]['name'] == '_rowid') {
                            $currentKey = $value;
                        }
                    }
                    if (($this->FX->genericKeys || $this->FX->primaryKeyField == '') && strlen(trim($currentKey)) < 1) {
                        $this->FX->currentData[] = $tempRow;
                    } else {
                        $this->FX->currentData[$currentKey] = $tempRow;
                    }
                }
                break;
            default:
                return new FX_Error("The action requested ({$action}) is not supported by OpenBase via FX.php.");
                break;
        }
        $this->FX->fxError = 0;
        return true;
    }

}

?>