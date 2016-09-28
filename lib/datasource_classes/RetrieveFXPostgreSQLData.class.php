<?php

require_once('RetrieveFXSQLData.class.php');

#### Part of FX.php #####################################################
#                                                                       #
#  License: Artistic License (included with release)                    #
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
        $postresql_res = pg_connect($connectString);
        if ($postresql_res == false) {
            return new FX_Error("Unable to connect to PostgreSQL server. (" . pg_last_error($postresql_res) . ")");
        }
        if ($this->retrieveMetadata && substr_count($action, '-db') == 0 && substr_count($action, 'names') == 0 && strlen(trim($this->FX->layout)) > 0) {
            $theResult = pg_query($postresql_res, "SELECT column_name, data_type, is_nullable FROM information_schema.columns WHERE table_name ='{$this->FX->layout}'");
            if (!$theResult) {
                return new FX_Error('Unable to access PostgreSQL column data: ' . pg_last_error($postresql_res));
            }
            $counter = 0;
            while ($tempRow = pg_fetch_array($theResult, $counter, PGSQL_ASSOC)) {
                $this->FX->fieldInfo[$counter]['name'] = $tempRow['column_name'];
                $this->FX->fieldInfo[$counter]['type'] = $tempRow['data_type'];
                $this->FX->fieldInfo[$counter]['emptyok'] = $tempRow['is_nullable'];
                $this->FX->fieldInfo[$counter]['maxrepeat'] = 1;
                ++$counter;
            }
        }
        switch ($action) {
            case '-delete':
            case '-edit':
            case '-find':
            case '-findall':
            case '-findany':
            case '-new':
                $this->FX->dataQuery = $this->BuildSQLQuery($action);
                if (FX::isError($this->FX->dataQuery)) {
                    return $this->FX->dataQuery;
                }
                if ($this->retrieveMetadata && substr_count($action, '-find') > 0) {
                    $theResult = pg_query($postresql_res, "SELECT COUNT(*) AS count FROM {$this->FX->layout}{$this->whereClause}");
                    if (!$theResult) {
                        return new FX_Error('Unable to retrieve row count: ' . pg_last_error($postresql_res));
                    }
                    $countRow = pg_fetch_assoc($theResult);
                    $this->FX->foundCount = $countRow['count'];
                }
            case '-sqlquery': // note that there is no preceding break, as we don't want to build a query
                $theResult = pg_query($this->FX->dataQuery);
                if (! $theResult) {
                    return new FX_Error('Invalid query: ' . pg_last_error($postresql_res));
                }
                // we got the found count above for generated SELECT queries, so get the residue here
                if ($action == '-sqlquery') {
                    $this->FX->foundCount = pg_num_rows($theResult);
                } elseif (substr_count($action, '-find') < 1) {
                    $this->FX->foundCount = pg_affected_rows($theResult);
                }
                if ($action == '-dup' || $action == '-edit') {
                    // pull in data on relevant record
                }
                $counter = 0;
                $currentKey = '';
                while ($tempRow = pg_fetch_array($theResult, $counter, PGSQL_ASSOC)) {
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
            case '-dup':
                break;
        }
        $this->FX->fxError = 0;
        return true;
    }

    function BuildSQLQuery ($action) {
        $limitClause = '';
        if ($action == '-findany') {
            if ($this->FX->selectColsSet) {
                $cols = $this->FX->selectColumns;
            }
            else $cols = '*';
            return "SELECT {$cols} FROM {$this->FX->layout} OFFSET FLOOR(RANDOM() * (SELECT COUNT(*) FROM {$this->FX->layout})) LIMIT 1";
        }
        elseif (is_numeric($this->FX->groupSize)) {
            $limitClause = " LIMIT {$this->FX->groupSize}";
            if ($this->FX->currentSkip > 0) $limitClause .= " OFFSET {$this->FX->currentSkip}";
        }
        $sqlQuery = parent::BuildSQLQuery($action, $limitClause);
        if ($action == '-dup' || $action == '-edit') {
            $sqlQuery .= " RETURNING *";
        }
        return $sqlQuery;
    }

}
