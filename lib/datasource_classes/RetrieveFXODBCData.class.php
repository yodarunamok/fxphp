<?php

require_once('RetrieveFXSQLData.class.php');

#### Part of FX.php #####################################################
#                                                                       #
#  License: Artistic License and addendum (included with release)       #
# Web Site: www.iviking.org                                             #
#                                                                       #
#########################################################################

class RetrieveFXODBCData extends RetrieveFXSQLData {

    function doQuery ($action) {
        $availableActions = array('-delete', '-edit', '-find', '-findall', '-new', '-sqlquery');

        if (! in_array(strtolower($action), $availableActions)) { // first off, toss out any requests for actions NOT supported under ODBC
            return new FX_Error("The action requested ({$action}) is not supported under ODBC via FX.php.");
        }
        $odbc_res = odbc_connect($this->FX->database, $this->FX->DBUser, $this->FX->DBPassword); // although username and password are optional for this function, FX.php expects them to be set
        if ($odbc_res == false) {
            return new FX_Error('Unable to connect to ODBC data source.');
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
                $odbc_result = odbc_exec($odbc_res, $this->FX->dataQuery);
                if (! $odbc_result) {
                    $tempErrorText = odbc_errormsg($odbc_res);
                    odbc_close($odbc_res);
                    return new FX_Error("Unsuccessful query: $this->FX->dataQuery ({$tempErrorText})");
                }
                $this->FX->foundCount = odbc_num_rows($odbc_result);
                $fieldCount = odbc_num_fields($odbc_result);
                if ($theResult < 0) {
                    $tempErrorText = odbc_errormsg($odbc_res);
                    odbc_close($odbc_res);
                    return new FX_Error("Unable to access field count for current ODBC query.  ({$tempErrorText})");
                }
                $odbc_columns = odbc_columns($odbc_res);
                if (! $odbc_columns) {
                    $tempErrorText = odbc_errormsg($odbc_res);
                    odbc_close($odbc_res);
                    return new FX_Error("Unable to retrieve column data via ODBC.  ({$tempErrorText})");
                }
                while (odbc_fetch_row($odbc_columns)) {
                    $fieldNumber = odbc_result($odbc_columns, 'ORDINAL_POSITION');
                    $this->FX->fieldInfo[$fieldNumber]['name'] = odbc_result($odbc_columns, 'COLUMN_NAME');
                    $this->FX->fieldInfo[$fieldNumber]['type'] = odbc_result($odbc_columns, 'TYPE_NAME');
                    $this->FX->fieldInfo[$fieldNumber]['emptyok'] = odbc_result($odbc_columns, 'IS_NULLABLE');
                    $this->FX->fieldInfo[$fieldNumber]['maxrepeat'] = 1;
                    $this->FX->fieldInfo[$fieldNumber]['extra'] = 'COLUMN_SIZE:' . odbc_result($odbc_columns, 'COLUMN_SIZE') . '|BUFFER_LENGTH:' . odbc_result($odbc_columns, 'BUFFER_LENGTH') . '|NUM_PREC_RADIX:' . odbc_result($odbc_columns, 'NUM_PREC_RADIX');
                }
                while (odbc_fetch_row($odbc_result)) {
                    $tempRow = array();
                    for ($i = 1; $i <= $fieldCount; ++$i) {
                        $theResult = odbc_result($odbc_result, $i);
                        if (! $this->FX->useInnerArray) {
                            $tempRow[$this->FX->fieldInfo[$i]['name']] = $theResult;
                        } else {
                            $tempRow[$this->FX->fieldInfo[$i]['name']] = array($theResult);
                        }
                        if ($this->FX->fieldInfo[$i]['name'] == $this->FX->primaryKeyField) {
                            $currentKey = $theResult;
                        }
                    }
                    if ($this->FX->genericKeys || $this->FX->primaryKeyField == '') {
                        $this->FX->currentData[] = $tempRow;
                    } else {
                        $this->FX->currentData[$currentKey] = $tempRow;
                    }
                }
                break;
            default:
                return new FX_Error("The action requested ({$action}) is not supported by FileMaker under ODBC via FX.php.");
                break;
        }
        $this->FX->fxError = 0;
        return true;
    }

}

?>