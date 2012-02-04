<?php

require_once('RetrieveFXData.class.php');

#### Part of FX.php #####################################################
#                                                                       #
#  License: Artistic License and addendum (included with release)       #
# Web Site: www.iviking.org                                             #
#                                                                       #
#########################################################################

// Do not use this class directly -- it is designed to be appropriately extended
class RetrieveFXSQLData extends RetrieveFXData {

    function BuildSQLSorts () {
        $currentOrderBy = '';

        if (count($this->FX->sortParams) > 0) {
            $counter = 0;
            $currentOrderBy .= ' ORDER BY ';
            foreach ($this->FX->sortParams as $key1 => $value1) {
                $field = '';
                $sortOrder = ''; // prevent IDE complaint. (msyk, Feb 1, 2012)
                foreach ($value1 as $key2 => $value2) {
                    $$key2 = $value2;
                }
                if ($counter > 0) {
                    $currentOrderBy .= ', ';
                }
                $currentOrderBy .= "{$field}";
                if (substr_count(strtolower($sortOrder), 'desc') > 0) {
                    $currentOrderBy .= ' DESC';
                }
                ++$counter;
            }
            return $currentOrderBy;
        }
    }

    function BuildSQLQuery ($action) {
        $currentLOP = 'AND';
        $logicalOperators = array();
        $LOPCount = 0;
        $currentSearch = '';
        $currentQuery = '';
        $counter = 0;
        $whereClause = '';

        $name = '';
        $value = '';
        $op = ''; // prevent IDE complaint. (msyk, Feb 1, 2012)

        switch ($action) {
            case '-find':
                foreach ($this->FX->dataParams as $key1 => $value1) {
                    foreach ($value1 as $key2 => $value2) {
                        $$key2 = $value2;
                    }
                    switch ($name) {
                        case '-lop':
                            $LOPCount = array_push($logicalOperators, $currentLOP);
                            $currentLOP = $value;
                            $currentSearch .= "(";
                            break;
                        case '-lop_end':
                            $currentLOP = array_pop($logicalOperators);
                            --$LOPCount;
                            $currentSearch .= ")";
                            break;
                        case '-recid':
                            if ($counter > 0) {
                                $currentSearch .= " {$currentLOP} ";
                            }
                            $currentSearch .= $this->FX->primaryKeyField . " = '" . $value . "'";
                            ++$counter;
                            break;
                        case '-script':
                        case '-script.prefind':
                        case '-script.presort':
                            return new FX_Error("The '-script' parameter is not currently supported for SQL.");
                            break;
                        default:
                            if ($op == "") {
                                $op = $this->FX->defaultOperator;
                            }
                            if ($counter > 0) {
                                $currentSearch .= " {$currentLOP} ";
                            }
                            switch ($op) {
                                case 'eq':
                                    $currentSearch .= $name . " = '" . $value . "'";
                                    break;
                                case 'neq':
                                    $currentSearch .= $name . " != '" . $value . "'";
                                    break;
                                case 'cn':
                                    $currentSearch .= $name . " LIKE '%" . $value . "%'";
                                    break;
                                case 'bw':
                                    $currentSearch .= $name . " LIKE '" . $value . "%'";
                                    break;
                                case 'ew':
                                    $currentSearch .= $name . " LIKE '%" . $value . "'";
                                    break;
                                case 'gt':
                                    $currentSearch .= $name . " > '" . $value . "'";
                                    break;
                                case 'gte':
                                    $currentSearch .= $name . " >= '" . $value . "'";
                                    break;
                                case 'lt':
                                    $currentSearch .= $name . " < '" . $value . "'";
                                    break;
                                case 'lte':
                                    $currentSearch .= $name . " <= '" . $value . "'";
                                    break;
                                default: // default is a 'begins with' search for historical reasons (default in FM)
                                    $currentSearch .= $name . " LIKE '" . $value . "%'";
                                    break;
                            }
                            ++$counter;
                            break;
                    }
                }
                while ($LOPCount > 0) {
                    --$LOPCount;
                    $currentSearch .= ")";
                }
                $whereClause = ' WHERE ' . $currentSearch; // set the $whereClause variable here, to distinguish this from a "finall" request
            case '-findall': //
                if ($this->FX->selectColsSet) {
                    $currentQuery = "SELECT {$this->FX->selectColumns} FROM {$this->FX->layout}{$whereClause}" . $this->BuildSQLSorts();
                } else {
                    $currentQuery = "SELECT * FROM {$this->FX->layout}{$whereClause}" . $this->BuildSQLSorts();
                }
                break;
            case '-delete':
                foreach ($this->FX->dataParams as $key1 => $value1) {
                    foreach ($value1 as $key2 => $value2) {
                        $$key2 = $value2;
                    }
                    if ($name == '-recid') {
                        $currentQuery = "DELETE FROM {$this->FX->layout} WHERE {$this->FX->primaryKeyField} = '{$value}'";
                    }
                }
                break;
            case '-edit':
                $whereClause = ' WHERE 1 = 0'; // if someone wants to update all records, they need to specify such
                $currentQuery = "UPDATE {$this->FX->layout} SET ";
                foreach ($this->FX->dataParams as $key1 => $value1) {
                    foreach ($value1 as $key2 => $value2) {
                        $$key2 = $value2;
                    }
                    if ($name == '-recid') {
                        $whereClause = " WHERE {$this->FX->primaryKeyField} = '{$value}'";
                    } else {
                        if ($counter > 0) {
                            $currentQuery .= ", ";
                        }
                        $currentQuery .= "{$name} = '{$value}'";
                        ++$counter;
                    }
                }
                $currentQuery .= $whereClause;
                break;
            case '-new':
                $tempColList = '(';
                $tempValueList = '(';
                foreach ($this->FX->dataParams as $key1 => $value1) {
                    $name = '';
                    $value = '';
                    foreach ($value1 as $key2 => $value2) {
                        $$key2 = $value2;
                    }
                    if ($name == '-recid') {
                        $currentQuery = "DELETE FROM {$this->FX->layout} WHERE {$this->FX->primaryKeyField} = '{$value}'";
                    }
                    if ($counter > 0) {
                        $tempColList .= ", ";
                        $tempValueList .= ", ";
                    }
                    $tempColList .= $name;
                    $tempValueList .= "'{$value}'";
                    ++$counter;
                }
                $tempColList .= ')';
                $tempValueList .= ')';
                $currentQuery = "INSERT INTO {$this->FX->layout} {$tempColList} VALUES {$tempValueList}";
                break;
        }
        $currentQuery .= ';';
        return $currentQuery;
    }

    function cleanUp() {
        // Clean up SQL queries here
    }

}

?>