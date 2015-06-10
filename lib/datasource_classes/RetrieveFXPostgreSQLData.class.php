<?php

require_once('RetrieveFXSQLData.class.php');

#### Part of FX.php #####################################################
#                                                                       #
#  License: Artistic License and addendum (included with release)       #
# Web Site: www.iviking.org                                             #
#                                                                       #
#########################################################################

class RetrieveFXPostgreSQLData extends RetrieveFXSQLData {

    function doQuery ($action) {
        $connectString = '';
        $unsupportedActions = array('-dbnames', '-layoutnames', '-scriptnames', '-dbopen', '-dbclose');

        if (in_array($action, $unsupportedActions)) {
            return new FX_Error("The requested Action ({$action}) is not supported in PostgreSQL via FX.php.");
        }
        if (strlen(trim($this->FX->dataServer)) > 0) {
            $connectString .= " host={$this->FX->dataServer}";
        }
        if (strlen(trim($this->FX->dataPort)) > 0) {
            $connectString .= " port={$this->FX->dataPort}";
        }
        if (strlen(trim($this->FX->database)) > 0) {
            $connectString .= " dbname={$this->FX->database}";
        }
        if (strlen(trim($this->FX->DBUser)) > 0) {
            $connectString .= " user={$this->FX->DBUser}";
        }
        if (strlen(trim($this->FX->DBPassword)) > 0) {
            $connectString .= " password={$this->FX->DBPassword}";
        }
        if (strlen(trim($this->FX->urlScheme)) > 0 && $this->FX->urlScheme == 'https') {
            $connectString .= " sslmode=require";
        }
        $postresql_res = @pg_connect($connectString);
        if ($postresql_res == false) {
            return new FX_Error("Unable to connect to PostgreSQL server. (" . pg_last_error($postresql_res) . ")");
        }
        $theResult = pg_query($postresql_res, "SELECT column_name, data_type, is_nullable FROM information_schema.columns WHERE table_name ='{$this->FX->layout}'");
        if (! $theResult) {
            return new FX_Error('Unable to access PostgreSQL column data: ' . pg_last_error($postresql_res));
        }
        $counter = 0;
        $keyPrecedence = 0;
        while ($tempRow = @pg_fetch_array($theResult, $counter, PGSQL_ASSOC)) {
            $this->FX->fieldInfo[$counter]['name'] = $tempRow['column_name'];
            $this->FX->fieldInfo[$counter]['type'] = $tempRow['data_type'];
            $this->FX->fieldInfo[$counter]['emptyok'] = $tempRow['is_nullable'];
            $this->FX->fieldInfo[$counter]['maxrepeat'] = 1;
            ++$counter;
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
                $theResult = pg_query($this->FX->dataQuery);
                if (! $theResult) {
                    return new FX_Error('Invalid query: ' . pg_last_error($postresql_res));
                }
                if (substr_count($action, '-find') > 0 || substr_count($this->FX->dataQuery, 'SELECT ') > 0) {
                    $this->FX->foundCount = pg_num_rows($theResult);
                } else {
                    $this->FX->foundCount = pg_affected_rows($theResult);
                }
                if ($action == '-dup' || $action == '-edit') {
                    // pull in data on relevant record
                }
                $counter = 0;
                $currentKey = '';
                while ($tempRow = @pg_fetch_array($theResult, $counter, PGSQL_ASSOC)) {
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
                    ++$counter;
                }
                break;
            case '-findany':
                break;
            case '-dup':
                break;
        }
        $this->FX->fxError = 0;
        return true;
    }

}

?>