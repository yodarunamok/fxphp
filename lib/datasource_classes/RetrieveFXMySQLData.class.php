<?php

require_once('RetrieveFXSQLData.class.php');

#### Part of FX.php #####################################################
#                                                                       #
#  License: Artistic License and addendum (included with release)       #
# Web Site: www.iviking.org                                             #
#                                                                       #
#########################################################################

class RetrieveFXMySQLData extends RetrieveFXSQLData {

    function doQuery ($action) {
        if (strlen(trim($this->FX->dataServer)) < 1) {
            return new FX_Error('No MySQL server specified.');
        }
        if (strlen(trim($this->FX->dataPort)) > 0) {
            $tempServer = $this->FX->dataServer . ':' . $this->FX->dataPort;
        } else {
            $tempServer = $this->FX->dataServer;
        }
        $mysql_res = @mysql_connect($tempServer, $this->FX->DBUser, $this->FX->DBPassword); // although username and password are optional for this function, FX.php expects them to be set
        if ($mysql_res == false) {
            return new FX_Error('Unable to connect to MySQL server.');
        }
        if ($action != '-dbopen') {
            if (! mysql_select_db($this->FX->database, $mysql_res)) {
                return new FX_Error('Unable to connect to specified MySQL database.');
            }
        }
        if (substr_count($action, '-db') == 0 && substr_count($action, 'names') == 0 && strlen(trim($this->FX->layout)) > 0) {
            $theResult = mysql_query('SHOW COLUMNS FROM ' . $this->FX->layout);
            if (! $theResult) {
                return new FX_Error('Unable to access MySQL column data: ' . mysql_error());
            }
            $counter = 0;
            $keyPrecedence = 0;
            while ($tempRow = mysql_fetch_assoc($theResult)) {
                $this->FX->fieldInfo[$counter]['name'] = $tempRow['Field'];
                $this->FX->fieldInfo[$counter]['type'] = $tempRow['Type'];
                $this->FX->fieldInfo[$counter]['emptyok'] = $tempRow['Null'];
                $this->FX->fieldInfo[$counter]['maxrepeat'] = 1;
                $this->FX->fieldInfo[$counter]['extra'] = $tempRow['Key'] . ' ' . $tempRow['Extra'];
                if ($this->FX->fuzzyKeyLogic) {
                    if (strlen(trim($this->FX->primaryKeyField)) < 1 || $keyPrecedence < 3) {
                        if (substr_count($this->FX->fieldInfo[$counter]['extra'], 'UNI ') > 0 && $keyPrecedence < 3) {
                            $this->FX->primaryKeyField = $this->FX->fieldInfo[$counter]['name'];
                            $keyPrecedence = 3;
                        } elseif (substr_count($this->FX->fieldInfo[$counter]['extra'], 'auto_increment') > 0 && $keyPrecedence < 2) {
                            $this->FX->primaryKeyField = $this->FX->fieldInfo[$counter]['name'];
                            $keyPrecedence = 2;
                        } elseif (substr_count($this->FX->fieldInfo[$counter]['extra'], 'PRI ') > 0 && $keyPrecedence < 1) {
                            $this->FX->primaryKeyField = $this->FX->fieldInfo[$counter]['name'];
                            $keyPrecedence = 1;
                        }
                    }
                }
                ++$counter;
            }
        }
        switch ($action) {
            case '-dbopen':
            case '-dbclose':
                return new FX_Error('Opening and closing MySQL databases not available.');
                break;
            case '-delete':
            case '-edit':
            case '-find':
            case '-findall':
            case '-findany':
            case '-new':
                if ($action == '-findany') {
                    $this->FX->dataQuery = 'SELECT ' . ($this->FX->selectColsSet?$this->FX->selectColumns:'*') . " FROM {$this->FX->layout} ORDER BY RAND() LIMIT 1;";
                } else {
                    $this->FX->dataQuery = $this->BuildSQLQuery($action);
                    if (FX::isError($this->FX->dataQuery)) {
                        return $this->FX->dataQuery;
                    }
                }
            case '-sqlquery': // note that there is no preceding break, as we don't want to build a query
                $theResult = mysql_query($this->FX->dataQuery);
                if ($theResult === false) {
                    return new FX_Error('Invalid query: ' . mysql_error());
                } elseif ($theResult !== true) {
                    if (substr_count($action, '-find') > 0 || substr_count($this->FX->dataQuery, 'SELECT ') > 0) {
                        $this->FX->foundCount = mysql_num_rows($theResult);
                    } else {
                        $this->FX->foundCount = mysql_affected_rows($theResult);
                    }
                    if ($action == '-dup' || $action == '-edit') {
                        // pull in data on relevant record
                    }
                    $currentKey = '';
                    while ($tempRow = mysql_fetch_assoc($theResult)) {
                        foreach ($tempRow as $key => $value) {
                            if ($this->FX->useInnerArray) {
                                $tempRow[$key] = array($value);
                            }
                            if ($key == $this->FX->primaryKeyField) {
                                $currentKey = $value;
                            }
                        }
                        if ($this->FX->genericKeys || $this->FX->primaryKeyField == '') {
                            $this->FX->currentData[] = $tempRow;
                        } else {
                            $this->FX->currentData[$currentKey] = $tempRow;
                        }
                    }
                } else {
                    $this->FX->currentData = array();
                }
                break;
            case '-dup':
                break;
        }
        $this->FX->fxError = 0;
        return true;
    }

}

?>