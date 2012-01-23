<?php
#### FX_Fuzzy_Debugger.php ##############################################
#                                                                       #
#       By: Chris Hansen                                                #
#  Version: 1.0                                                         #
#     Date: 25 Feb 2008                                                 #
#  License: Artistic License and addendum (included with release)       #
# Web Site: www.iviking.org                                             #
#  Details: FX_Fuzzy_Debugger is part of the FX.php distribution.  It   #
#          is designed to work with both FX.php and the FileMaker API   #
#          for PHP.  For complete details about this class, please      #
#          visit www.iviking.org.                                       #
#                                                                       #
#########################################################################

require_once('Developer/FMErrors.php');

if (version_compare(phpversion(), '5.0') < 0) {
    eval('
        function clone($object) {
            return $object;
        }
    ');
}

define('FX_CONNECTION', 'FX');
define('FILEMAKER_API_CONNECTION', 'FAP');

class FX_Fuzzy_Debugger
{
    var $ivikingContact = 'FX@iviking.org';
    var $currentErrorCode = -2;
    var $fixes = array();
    var $currentConnection = null;
    var $currentDataSet = null;
    var $currentDatabase = '';
    var $currentLayout = '';
    var $currentFieldsArray = array();
    var $connectionType = false;
    var $fuzzyOut = false;
    var $databasesArray = array();
    var $layoutsArray = array();
    var $fieldsArray = array();
    var $similarityThreshold = 5;

    function FX_Fuzzy_Debugger (&$fmConnection, &$dataSet='') // When using with the FileMaker API for PHP, pass both the connection, and the returned data set
    {
        if ((bool)(is_object($fmConnection) && (strtolower(get_class($fmConnection)) == 'filemaker' || is_subclass_of($fmConnection, 'filemaker')))) {
            require_once('FileMaker.php');
            $this->connectionType = FILEMAKER_API_CONNECTION;
            $this->currentConnection = clone($fmConnection);
            $this->currentDataSet = $dataSet;
            if (FileMaker::isError($dataSet)) {
                if (! isset($dataSet->code) || strlen(trim($dataSet->code)) < 1) {
                    $currentErrorMessage = "<p>A connection or XML error occured during your FileMaker query.<br />\n";
                    $currentErrorMessage .= "You may be able to get additional information by performing the same query using FX.php,<br />\n";
                    $currentErrorMessage .= "combined with a DEBUG constant and php's print_r() function.<br />\n";
                    $currentErrorMessage .= "Also, check your <strong>FileMaker Server Advanced configuration</strong>, and verify the <strong>server address</strong> used.</p>\n";
                    return $currentErrorMessage;
                } else {
                    $this->currentErrorCode = $dataSet->code;
                }
            } else {
                $this->currentErrorCode = 0;
            }
            $this->currentDatabase = $this->currentConnection->getProperty('database');
            $this->currentLayout = '';
        } elseif ((bool)(is_object($fmConnection) && (strtolower(get_class($fmConnection)) == 'fx' || is_subclass_of($fmConnection, 'fx')))) {
            require_once('FX.php');
            $this->connectionType = FX_CONNECTION;
            $this->currentConnection = clone($fmConnection);
            if (is_array($dataSet) && isset($dataSet['errorCode'])) {
                $this->currentErrorCode = $dataSet['errorCode'];
            } else {
                $this->currentErrorCode = $fmConnection->lastErrorCode;
            }
            $this->currentDatabase = $this->currentConnection->database;
            $this->currentLayout = $this->currentConnection->layout;
            foreach ($this->currentConnection->lastQueryParams as $tempParam) {
                if (substr($tempParam['name'], 0, 1) != '-') { // as long as the current field name doesn't begin with a '-' (FM reserved), add it to the list of fields
                    $this->currentFieldsArray[] = $tempParam['name'];
                }
            }
        } else {
            $currentErrorMessage = "<p>The FX Fuzzy Debugger does not support the type of connection that was passed in.<br />\n";
            $currentErrorMessage .= "Double check that the first parameter is either an FX object or a FileMaker object.</p>\n";
            $this->fuzzyOut = $currentErrorMessage;
            return;
        }
        $this->fuzzyOut =  $this->ProcessErrorCode();
    }

    function ProcessErrorCode ()
    {
        global $errorsList;

        $errorTrapped = false;
        $processingOutput = "<p>Begin FX Fuzzy Debugger Error processing...</p>";

        $processingActionsArray = array();
        $dbProcessor = 'CheckDBList';
        $layoutProcessor = 'CheckLayoutsList';
        $fieldProcessor = 'CheckFieldsList';

        $processingOutput .= "<p>Error Code {$this->currentErrorCode}:<br />{$errorsList[$this->currentErrorCode]}</p>\n";
        switch ($this->currentErrorCode) {
            case -2:
                $processingOutput .= "<p>No FX Action has been performed.  Be sure to specify an action and try again.</p>\n";
                break;
            case -1:
                $processingOutput .= "<p>This is officially an unknown error.  If possible, please contact {$this->ivikingContact} with details.</p>\n";
                break;
            case 0:
                return false;
                break;
            case 9: // insufficient privileges
                $processingActionsArray = array($dbProcessor, $layoutProcessor);
                $processingOutput .= "<p>Be sure that permissions are set properly.  Pay special attention to any areas mentioned below.</p>\n";
                break;
            case 22: // (pseudo?) error returned (as far as I know) only by the FileMaker API for PHP
                $processingOutput .= "<p>Observed only from the FileMaker API for PHP when a direct connection by FX.php to the FM Server would return other than XML.<br />\n";
                $processingOutput .= "<p>Check the <strong>user name</strong> and <strong>password</strong> combination specified.</p>\n";
                $processingOutput .= "<p>This error has not been observed when using FX.php; if this error was observed via an FX.php query, please contact {$this->ivikingContact} with details.</p>\n";
                break;
            case 102: // Field is missing
                $processingActionsArray = array($fieldProcessor);
                $processingOutput .= "<p>Double check the spelling of the fields specified, and be sure they're on the layout specified.<br />\n";
                $processingOutput .= "Also, be sure that the <strong>user name</strong> and <strong>password</strong> combination specified has permission to access the specified fields.</p>\n";
                // return $currentErrorMessage;
                break;
            case 105: // Layout Missing
                $processingActionsArray = array($layoutProcessor);
                $processingOutput .= "<p>Be sure that the <strong>user name</strong> and <strong>password</strong> combination specified has permission to access &quot;{$this->currentLayout}&quot;.</p>\n";
                break;
            case 401: // No Records Found
                $processingOutput .= "<p>This is a simple &quot;No Records Found&quot; error.  Most likely, you just need to add trapping for situations where no records are found.</p>\n";
                break;
            case 802: // Unable to open file
                $processingActionsArray = array($dbProcessor);
                $processingOutput .= "<p>Be sure that you've specified the correct FileMaker Server address, that the server is running, and that the database specified is spelled correctly.<br />\n";
                $processingOutput .= "Also, be sure that the credentials used have proper extended permissions associated with them.</p>\n";
                break;
            default:
                $processingOutput .= "<p>This error is not yet supported within the FX Fuzzy Debugger.  If possible, please contact {$this->ivikingContact} with details.</p>\n";
                break;
        }
        foreach ($processingActionsArray as $key => $tempFuction) {
            $tempFunctionReturn = call_user_func(array(&$this, $tempFuction));
            if ($tempFunctionReturn !== true) {
                if (is_array($tempFunctionReturn)) {
                    if (! is_array($tempFunctionReturn[0])) {
                        $tempFunctionReturn[0] = array($tempFunctionReturn[0]);
                    }
                    foreach ($tempFunctionReturn[0] as $tempDBElement) {
                        $nearestMatch = $this->FindNearestMatch($tempDBElement, $tempFunctionReturn[1]);
                        if ($nearestMatch === 0) {
                            $processingOutput .= "<p>Supposedly there wasn't a match for &quot;{$tempDBElement}&quot;, but we found one.  If possible, please contact {$this->ivikingContact} with details.</p>\n";
                        } elseif ($nearestMatch !== false) {
                            $processingOutput .= "<p>You entered &quot;{$tempDBElement}&quot;, did you mean &quot;{$nearestMatch}&quot;?</p>\n";
                        } else {
                            $processingOutput .= "<p>No near matches were found for &quot;{$tempDBElement}&quot;.  The problem may be related to permissions.</p>\n";
                        }
                    }
                } else {
                    $processingOutput .= $tempFunctionReturn;
                }
                break;
            }
        }
        $processingOutput .= "<p>FX Fuzzy Debugger error processing finished.</p>";
        return $processingOutput;
    }

    function CheckDBList ()
    {
        if (count($this->databasesArray) < 1) {
            if ($this->connectionType == FILEMAKER_API_CONNECTION) {
                $this->databasesArray = $this->currentConnection->listDatabases();
            } elseif ($this->connectionType == FX_CONNECTION) {
                $tempDBList = $this->currentConnection->DoFXAction('view_database_names');
                foreach ($tempDBList as $value) {
                    $this->databasesArray[] = $value['DATABASE_NAME'];
                }
            } else {
                return "<p>Unrecognized connection type when checking Databases.</p>";
            }
        }
        if (array_search($this->currentDatabase, $this->databasesArray) !== false) {
            return true;
        } else {
            return array($this->currentDatabase, $this->databasesArray);
        }
    }

    function CheckLayoutsList ()
    {
        if (count($this->layoutsArray) < 1) {
            if ($this->connectionType == FILEMAKER_API_CONNECTION) {
                $this->layoutsArray = $this->currentConnection->listLayouts();
            } elseif ($this->connectionType == FX_CONNECTION) {
                $tempLayoutsList = $this->currentConnection->DoFXAction('view_layout_names');
                foreach ($tempLayoutsList as $value) {
                    $this->layoutsArray[] = $value['LAYOUT_NAME'];
                }
            } else {
                return "<p>Unrecognized connection type when checking Layouts.</p>";
            }
        }
        if (array_search($this->currentLayout, $this->layoutsArray) !== false) {
            return true;
        } else {
            return array($this->currentLayout, $this->layoutsArray);
        }
    }

    function CheckFieldsList ()
    {
        $unknownFieldsArray = array();
        if ($this->connectionType == FILEMAKER_API_CONNECTION) {
            return "<p>The FileMaker API for PHP does not yet offer the functions needed to support detailed field name checking.</p>";
        } elseif ($this->connectionType == FX_CONNECTION) {
            $tempFieldsList = $this->currentConnection->DoFXAction('view_layout_objects');
            foreach ($this->currentConnection->lastFields as $value) {
                $this->fieldsArray[] = $value['name'];
            }
        } else {
            return "<p>Unrecognized connection type when checking Fields.</p>";
        }
        foreach ($this->currentFieldsArray as $currentField) {
            if (array_search($currentField, $this->fieldsArray) === false) {
                $unknownFieldsArray[] = $currentField;
            }
        }
        if (count($unknownFieldsArray) > 0) {
            return array($unknownFieldsArray, $this->fieldsArray);
        } else {
            return true;
        }
    }

    function FindNearestMatch ($currentItem, $searchArray)
    {
        $nearestDistance = -1;
        $nearestMatch = false;
        if (! is_array($searchArray)) { // if $searchArray isn't an array, it will cause us some grief...
            return false;
        }
        foreach ($searchArray as $key => $tempItem) {
            $tempDistance = levenshtein($currentItem, $tempItem);
            if ($tempDistance == 0) {
                return 0;
            } elseif (($nearestDistance == -1 || $tempDistance < $nearestDistance) && $tempDistance <= $this->similarityThreshold) {
                // we only update our match guess if the current option is closer _and_ we're within the specified threshold
                $nearestDistance = $tempDistance;
                $nearestMatch = $tempItem;
            }
        }
        return $nearestMatch;
    }

    function SetSimilarityThreshold ($newThreshold)
    {
        $this->similarityThreshold = $newThreshold;
        return true;
    }

}
?>