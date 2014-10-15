<?php
#### FX.php #############################################################
#                                                                       #
#       By: Chris Hansen with Chris Adams, G G Thorsen, Masayuki Nii,   #
#          and others                                                   #
#  Version: 6.0                                                         #
#     Date: 3 Feb 2012                                                  #
#  License: Artistic License and addendum (included with release)       #
# Web Site: www.iviking.org                                             #
#  Details: FX is a free open-source PHP class for accessing FileMaker  #
#          and other databases.  For complete details about this class, #
#          please visit www.iviking.org.                                #
#                                                                       #
#########################################################################

define("FX_VERSION", '6.0');                                            // Current version information for FX.php.  New constants as of version 4.0.
define("FX_VERSION_FULL", 'FX.php version ' . FX_VERSION . ' (3 Feb 2012) by Chris Hansen, Chris Adams, G G Thorsen, Masayuki Nii, and others.');

require_once('FX_Error.php');                                           // FX.php includes object based error handling.  See FX_Error.php for more info.

if (! defined('DEBUG_FUZZY')) {                                         // This version of FX.php includes the FX Fuzzy Debugger (turned off by default.)
    define('DEBUG_FUZZY', false);
}

require_once('FX_constants.php');                                       // The constants in this file are designed to be used with DoFXAction()

define("EMAIL_ERROR_MESSAGES", FALSE);                                  // Set this to TRUE to enable emailing of specific error messages.
define("DISPLAY_ERROR_MESSAGES", TRUE);                                 // Set this to FALSE to display the $altErrorMessage to the user.
$webmasterEmailAddress = 'webmaster@yourdomain.com';                    // If you set the above to TRUE, enter the appropriate email address on this line.
$emailFromAddress = 'you@yourdomain.com';                               // Sets who the error message will show as the sender.

function EmailError ($errorText) {
    global $webmasterEmailAddress;
    global $emailFromAddress;

    if (EMAIL_ERROR_MESSAGES) {
        $emailSubject = "PHP Server Error";
        $emailMessage = "The following error just occured:\r\n\r\nMessage: {$errorText}\r\n\r\n**This is an automated message**";
        $emailStatus = mail($webmasterEmailAddress, $emailSubject, $emailMessage, "From: $emailFromAddress\r\n");
    }
}

function EmailErrorHandler ($FXErrorObj) {
    $altErrorMessage = 'The Server was unable to process your request.<br />The WebMaster has been emailed.<br /> Thank you for your patience.';

    EmailError($FXErrorObj->message);
    if (DISPLAY_ERROR_MESSAGES) {
        echo($FXErrorObj->message);
    } else {
        echo($altErrorMessage);
    }
    return true;
}

class FX {

    // These are the basic database variables.
    var $dataServer = "";
    var $dataServerType = 'fmpro';
    var $dataServerVersion = 7;
    var $dataPort;
    var $dataPortSuffix;
    var $urlScheme;
    var $useSSLProtocol = false;
    var $verifyPeer = true;
    var $database = "";
    var $layout = ""; // the layout to be accessed for FM databases.  For SQL, the table to be accessed.
    var $responseLayout = "";
    var $groupSize;
    var $currentSkip = 0;
    var $defaultOperator = 'bw';
    var $findquerynumber = 1;
    var $findquerystring = '';
    var $dataParams = array();
    var $sortParams = array();
    var $actionArray = array(
            // for backwards compatibility
            "-delete"               =>"-delete",
            "-dup"                  =>"-dup",
            "-edit"                 =>"-edit",
            "-find"                 =>"-find",
            "-findall"              =>"-findall",
            "-findany"              =>"-findany",
            '-findquery'            =>'-findquery',
            "-new"                  =>"-new",
            "-view"                 =>"-view",
            "-dbnames"              =>"-dbnames",
            "-layoutnames"          =>"-layoutnames",
            "-scriptnames"          =>"-scriptnames",
            "-sqlquery"             =>"-sqlquery",
            // new params for DoFXAction
            "delete"                =>"-delete",
            "duplicate"             =>"-dup",
            "update"                =>"-edit",
            "perform_find"          =>"-find",
            "show_all"              =>"-findall",
            "show_any"              =>"-findany",
            "new"                   =>"-new",
            "view_layout_objects"   =>"-view",
            "view_database_names"   =>"-dbnames",
            "view_layout_names"     =>"-layoutnames",
            "view_script_names"     =>"-scriptnames"
        );

    // Variables to help with SQL queries
    var $primaryKeyField = '';
    var $modifyDateField = '';
    var $dataKeySeparator = '';
    var $fuzzyKeyLogic = false;
    var $genericKeys = false;
    var $selectColsSet = false;
    var $selectColumns = '';

    // These are the variables to be used for storing the retrieved data.
    var $fieldInfo = array();
    var $currentData = array();
    var $valueLists = array();
    var $totalRecordCount = -1;
    var $foundCount = -1;
    var $dateFormat = "";
    var $timeFormat = "";
    var $dataQuery = "";

    // Variables used to track how data is moved in and out of FileMaker.  Used when UTF-8 just doesn't cut it (as when working with Japanese characters.)
    // This and all related code were submitted by Masayuki Nii.
    // These used to be blank by default, but Finn Løvenkrands found that caused problems with some characters.
    var $charSet = 'UTF-8';                                             // Determines how outgoing data is encoded.
    var $dataParamsEncoding = 'UTF-8';                                  // Determines how incoming data is encoded.

    var $remainNames = array();    // Added by Masayuki Nii(nii@msyk.net) Dec 18, 2010
    var $remainNamesReverse = array();    // Added by Masayuki Nii(nii@msyk.net) Jan 23, 2010
    var $portalAsRecord =false;    // Added by Masayuki Nii(nii@msyk.net) Dec 18, 2010

    var $usePortalIDs = false;    // for use with the RetrieveFM7VerboseData.class "fmalt"

    // Flags and Error Tracking
    var $fieldCount = 0;
    var $fxError = 'No Action Taken';
    var $errorTracking = 0;
    var $useInnerArray = null;                                              // Do NOT change this variable directly.  Use FlattenInnerArray() or the appropriate param of action method.
    var $useReturnJSONResult = false;
    var $useReturnJSONFullArrayResult = false;
    var $useComma2Period = false;

    // These variables will be used if you need a password to access your data.
    var $DBUser = 'FX';
    var $DBPassword = '';                                                 // This can be left blank, or replaced with a default or dummy password.
    var $userPass = '';

    // These variables are related to sending data to FileMaker via a Post.
    var $defaultPostPolicy = true;
    var $isPostQuery;
    var $defaultFOpenPolicy = false;
    var $isFOpenQuery;
    var $useCURL = true;
    var $customPrimaryKey = '';

    // When returning your data via the 'object' return type, these variables will contain the database meta data
    var $lastLinkPrevious = '';
    var $lastLinkNext = '';
    var $lastFoundCount = -2;
    var $lastFields = array();
    var $lastURL = '';
    var $lastQuery = '';
    var $lastQueryParams = array();
    var $lastErrorCode = -2;
    var $lastValueLists = array();
    var $lastDebugMessage = '';

    // Other variables
    var $fuzzyFXPass = ''; // this is to handle the fact that I couldn't provide a default value for a pass-by-value param in PHP4

    // Constructor
    function FX ($dataServer, $dataPort=80, $dataType='', $dataURLType='') {
        $this->dataServer = $dataServer;
        $this->dataPort = $dataPort;
        $this->dataPortSuffix = ":" . $dataPort;
        if (strlen($dataType) > 0) {
            $this->dataServerType = substr(strtolower($dataType), 0, 5);
        }
        if ($this->dataServerType == 'fmpro' || $this->dataServerType == 'fmalt') {
            $this->dataServerVersion = intval(str_replace($this->dataServerType, '', strtolower($dataType)));
        } else {
            $this->dataServerVersion = 0;
        }
        if (((strlen($dataURLType) > 0 && $this->dataServerVersion >= 7 && $this->dataServerType == 'fmpro') || ($this->dataServerType == 'fmalt')) && strtolower($dataURLType) == 'https') {
            $this->useSSLProtocol = true;
            $this->urlScheme = 'https';
        } else {
            $this->useSSLProtocol = false;
            $this->urlScheme = 'http';
        }

        $this->ClearAllParams();
        $this->lastDebugMessage = '<p>Instantiating FX.php.</p>';
    }

    function BuildExtendedChar ($byteOne, $byteTwo="\x00", $byteThree="\x00", $byteFour="\x00") {
        if (ord($byteTwo) >= 128) {
            $tempChar = substr(decbin(ord($byteTwo)), -6);
            if (ord($byteThree) >= 128) {
                $tempChar .= substr(decbin(ord($byteThree)), -6);
                if (ord($byteFour) >= 128) {
                    $tempChar .= substr(decbin(ord($byteFour)), -6);
                    $tempChar = substr(decbin(ord($byteOne)), -3) . $tempChar;
                } else {
                    $tempChar = substr(decbin(ord($byteOne)), -4) . $tempChar;
                }
            } else {
                $tempChar = substr(decbin(ord($byteOne)), -5) . $tempChar;
            }
        } else $tempChar = $byteOne;
        $tempChar = '&#' . bindec($tempChar) . ';';
        return $tempChar;
    }

    function ClearAllParams () {
        $this->userPass = "";
        $this->dataQuery = "";
        $this->dataParams = array();
        $this->sortParams = array();
        $this->fieldInfo = array();
        $this->valueLists = array();
        $this->fieldCount = 0;
        $this->currentSkip = 0;
        $this->currentData = array();
        $this->columnCount = -1;
        $this->isPostQuery = $this->defaultPostPolicy;
        $this->isFOpenQuery = $this->defaultFOpenPolicy;
        $this->primaryKeyField = '';
        $this->modifyDateField = '';
        $this->dataKeySeparator = '';
        $this->fuzzyKeyLogic = false;
        $this->genericKeys = false;
        $this->useInnerArray = null;
        $this->remainNames = array();    // Added by Masayuki Nii(nii@msyk.net) Dec 18, 2010
        $this->remainNamesReverse = array();    // Added by Masayuki Nii(nii@msyk.net) Jan 23, 2011
        $this->portalAsRecord = false;    // Added by Masayuki Nii(nii@msyk.net) Dec 18, 2010
        $this->findquerynumber = 1; // added by Nick Salonen
        $this->findquerystring = ''; // added by Nick Salonen
    }

    function ErrorHandler ($errorText) {
        $this->fxError = $errorText;
        $this->errorTracking = 3300;
        return $errorText;
    }

    function ExecuteQuery ($action) {
        switch ($this->dataServerType) {
            case 'fmpro':
                if ($this->dataServerVersion >= 7) {
                    require_once('datasource_classes/RetrieveFM7Data.class.php');
                    $datasourceClassName = 'RetrieveFM7Data';
                    $datasourceDescription = 'FileMaker Server 7+';
                } else {
                    require_once('datasource_classes/RetrieveFM5Data.class.php');
                    $datasourceClassName = 'RetrieveFM5Data';
                    $datasourceDescription = 'FileMaker Pro 5/6';
                }
                break;
            case 'fmalt':
                // calls to FMView require this fix as of server version 12 to 12.0v2, so far
                if ($action == '-view')
                {
                    require_once('datasource_classes/RetrieveFM7Data.class.php');
                    $datasourceClassName = 'RetrieveFM7Data';
                    $datasourceDescription = 'FileMaker Server 7+';
                } else {
                    require_once('datasource_classes/RetrieveFM7VerboseData.class.php');
                    $datasourceClassName = 'RetrieveFM7VerboseData';
                    $datasourceDescription = 'FileMaker Server 7+ Verbose';
                }
                break;
            case 'openb':
                require_once('datasource_classes/RetrieveFXOpenBaseData.class.php');
                $datasourceClassName = 'RetrieveFXOpenBaseData';
                $datasourceDescription = 'OpenBase';
                break;
            case 'mysql':
                require_once('datasource_classes/RetrieveFXMySQLData.class.php');
                $datasourceClassName = 'RetrieveFXMySQLData';
                $datasourceDescription = 'MySQL';
                break;
            case 'postg':
                require_once('datasource_classes/RetrieveFXPostgreSQLData.class.php');
                $datasourceClassName = 'RetrieveFXPostgreSQLData';
                $datasourceDescription = 'PostgreSQL';
                break;
            case 'odbc':
                require_once('datasource_classes/RetrieveFXODBCData.class.php');
                $datasourceClassName = 'RetrieveFXODBCData';
                $datasourceDescription = 'ODBC';
                break;
            case 'cafep':
                require_once('datasource_classes/RetrieveCafePHP4pcData.class.php');
                $datasourceClassName = 'RetrieveCafePHP4pcData';
                $datasourceDescription = 'CAFEphp';
                break;
        }

        // Query and handle data
        if ((defined("DEBUG") and DEBUG) or DEBUG_FUZZY) {
            $currentDebugString = "<p>Accessing {$datasourceDescription} data.</p>\n";
            $this->lastDebugMessage .= $currentDebugString;
            if (defined("DEBUG") and DEBUG) {
                echo $currentDebugString;
            }
        }
        $queryObject = new $datasourceClassName($this);
        $result = $queryObject->doQuery($action);
        if (FX::isError($result)) {
            return $result;
        }
        $queryObject->cleanUp();

    }

    function BuildLinkQueryString () {
        $tempQueryString = '';
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST') {
            $paramSetCount = 0;
            $appendFlag = true;
            foreach ($_POST as $key => $value) {
                if ($appendFlag && strcasecmp($key, '-foundSetParams_begin') != 0 && strcasecmp($key, '-foundSetParams_end') != 0) {
                    if (is_array($value))
                    {
                        foreach($value as $innertkey => $innertvalue)
                        {
                            $tempQueryString .= urlencode($key.'[]') . '='.$innertvalue.'&';
                        }
                    } else {
                        $tempQueryString .= urlencode($key) . '=' . urlencode($value) . '&';
                    }
                } elseif (strcasecmp($key, '-foundSetParams_begin') == 0) {
                    $appendFlag = true;
                    if ($paramSetCount < 1) {
                        $tempQueryString = '';
                        ++$paramSetCount;
                    }
                } elseif (strcasecmp($key, '-foundSetParams_end') == 0) {
                    $appendFlag = false;
                }
            }
        } else {
            $beginTagLower = strtolower('-foundSetParams_begin');
            $endTagLower = strtolower('-foundSetParams_end');
            if (! isset($_SERVER['QUERY_STRING'])) {
                $_SERVER['QUERY_STRING'] = '';
            }
            $queryStringLower = strtolower($_SERVER['QUERY_STRING']);
            if (substr_count($queryStringLower, $beginTagLower) > 0 && substr_count($queryStringLower, $beginTagLower) == substr_count($queryStringLower, $endTagLower)) {
                $tempOffset = 0;
                for ($i = 0; $i < substr_count($queryStringLower, $beginTagLower); ++$i) {
                    $tempBeginFoundSetParams = strpos($queryStringLower, $beginTagLower, $tempOffset);
                    $tempEndFoundSetParams = strpos($queryStringLower, $endTagLower, $tempOffset) + (strlen($endTagLower) - 1);
                    $tempFoundSetParams = substr($_SERVER['QUERY_STRING'], $tempBeginFoundSetParams, ($tempEndFoundSetParams - $tempBeginFoundSetParams) + 1);
                    $tempQueryString .= preg_replace("/(?i)$beginTagLower=[^&]*&(.*)&$endTagLower/", "\$1", $tempFoundSetParams);
                    $tempOffset = $tempEndFoundSetParams;
                }
            } else {
                $tempQueryString = $_SERVER['QUERY_STRING'];
            }
            $tempQueryString = preg_replace("/skip=[\d]*[&]?/", "", $tempQueryString);
        }
        return $tempQueryString;
    }

    function AssembleDataSet ($returnData) {
        $dataSet = array();
        $FMNext = $this->currentSkip + $this->groupSize;
        $FMPrevious = $this->currentSkip - $this->groupSize;

        switch ($returnData) {
            case 'object':
                $dataSet = $this->currentData;

                if ($FMNext < $this->foundCount || $FMPrevious >= 0) {
                    $tempQueryString = $this->BuildLinkQueryString();
                } else {
                    $tempQueryString = '';
                }
                if ($FMNext >= $this->foundCount) {
                    $this->lastLinkNext = "";
                } else {
                    $this->lastLinkNext = $_SERVER['SCRIPT_NAME'] . "?skip=$FMNext&{$tempQueryString}";
                }
                if ($FMPrevious < 0) {
                    $this->lastLinkPrevious = "";
                } else {
                    $this->lastLinkPrevious = $_SERVER['SCRIPT_NAME'] . "?skip=$FMPrevious&{$tempQueryString}";
                }

                $this->lastFoundCount = $this->foundCount;
                $this->lastFields = $this->fieldInfo;
                $this->lastQuery = $this->dataQuery;
                $this->lastQueryParams = $this->dataParams;
                $this->lastErrorCode = $this->fxError;
                $this->lastValueLists = $this->valueLists;

                if (DEBUG_FUZZY && $this->lastErrorCode != 0) {
                    require_once('FX_Fuzzy_Debugger.php');
                    $fuzzyErrorData = new FX_Fuzzy_Debugger($this, $this->fuzzyFXPass);
                    if ($fuzzyErrorData->fuzzyOut !== false) {
                        $this->lastDebugMessage .= $fuzzyErrorData->fuzzyOut;
                    }
                }

                break;
            case 'full':
                $dataSet['data'] = $this->currentData;
                if (defined('FX_OBJECTIVE')) {
                    $dataSet['object'] = new ObjectiveFX($dataSet['data']);
                }
            case 'basic':
                if ($FMNext < $this->foundCount || $FMPrevious >= 0) {
                    $tempQueryString = $this->BuildLinkQueryString();
                } else {
                    $tempQueryString = '';
                }
                if ($FMNext >= $this->foundCount) {
                    $dataSet['linkNext'] = "";
                } else {
                    $dataSet['linkNext'] = $_SERVER['SCRIPT_NAME'] . "?skip=$FMNext&{$tempQueryString}";
                }

                if ($FMPrevious < 0) {
                    $dataSet['linkPrevious'] = "";
                } else {
                    $dataSet['linkPrevious'] = $_SERVER['SCRIPT_NAME'] . "?skip=$FMPrevious&{$tempQueryString}";
                }

                $this->lastFoundCount = $this->foundCount;
                $this->lastFields = $this->fieldInfo;
                $this->lastQuery = $this->dataQuery;
                $this->lastQueryParams = $this->dataParams;
                $this->lastErrorCode = $this->fxError;
                $this->lastValueLists = $this->valueLists;

                $dataSet['foundCount'] = $this->foundCount;
                $dataSet['fields'] = $this->fieldInfo;
                $dataSet['URL'] = $this->lastURL;
                $dataSet['query'] = $this->dataQuery;
                $dataSet['errorCode'] = $this->fxError;
                $dataSet['valueLists'] = $this->valueLists;

                if (DEBUG_FUZZY && $this->lastErrorCode != 0) {
                    require_once('FX_Fuzzy_Debugger.php');
                    $fuzzyErrorData = new FX_Fuzzy_Debugger($this, $this->fuzzyFXPass);
                    if ($fuzzyErrorData !== false) {
                        $this->lastDebugMessage .= $fuzzyErrorData;
                    }
                }

                break;
        }

        $this->ClearAllParams();
/*
// Added to github 4/4-2014
        if( $this->useReturnJSONFullArrayResult == true ) {
            // Not sure if array_values() are needed
            $dataSet = json_encode( array_values( $dataSet ) );
        }
 */
        return $dataSet;
    }

    function FMAction ($Action, $returnDataSet, $returnData, $useInnerArray) {
        if ($this->useInnerArray === null) {
            $this->useInnerArray = $useInnerArray;
        }
        $queryResult = $this->ExecuteQuery($this->actionArray[strtolower($Action)]);
        if (FX::isError($queryResult)){
            if (EMAIL_ERROR_MESSAGES) {
                EmailErrorHandler($queryResult);
            }
            return $queryResult;
        }
        if ($returnDataSet) {
            $dataSet = $this->AssembleDataSet($returnData);
            return $dataSet;
        } else {
            $this->ClearAllParams();
            return true;
        }
    }

    // The functions above (with the exception of the FX constructor) are intened to be called from other functions within FX.php (i.e. private functions).
    // The functions below are those which are intended for general use by developers (i.e. public functions).
    // Once I'm quite sure that most people are using PHP5, I'll release a version using the improved object model of PHP5.

    function isError($data) {
        return (bool)(is_object($data) &&
                      (strtolower(get_class($data)) == 'fx_error' ||
                      is_subclass_of($data, 'fx_error')));
    }

    function SetCharacterEncoding ($encoding) {         // This is the more general of the encoding functions (see notes below, and the functions documentation.)
        $this->charSet = $encoding;
        $this->dataParamsEncoding = $encoding;

        // When using a different type of encoding downstream than upstream, you must call this function -- SetCharacterEncoding() --
        // to set downstream encoding (the way data FROM the database is encoded) BEFORE calling SetDataParamsEncoding().
        // When this function is called alone, both instance valiables are set to the same value.
        // *IMPORTANT*: Using either this function or the next one is moot unless you have multi-byte support compliled into PHP (e.g. Complete PHP).
    }

    function SetDataParamsEncoding ($encoding) {        // SetDataParamsEncoding() is used to specify the encoding of parameters sent to the database (upstream encoding.)
        $this->dataParamsEncoding = $encoding;
    }

    // the layout parameter is equivalent to the table to be used in SQL queries
    function SetDBData ($database, $layout="", $groupSize=50, $responseLayout="") {
        $this->database = $database;
        $this->layout = $layout;
        $this->groupSize = $groupSize;
        $this->responseLayout = $responseLayout;
        $this->ClearAllParams();
        $this->lastDebugMessage .= '<p>Configuring database connection...</p>';
    }

    function SetDBPassword ($DBPassword, $DBUser='FX') { // Note that for historical reasons, password is the FIRST parameter for this function
        if ($DBUser == '') {
            $DBUser = 'FX';
        }
        $this->DBPassword = $DBPassword;
        $this->DBUser = $DBUser;
        $this->lastDebugMessage .= '<p>Setting user name and password...</p>';
    }

    function SetDBUserPass ($DBUser, $DBPassword='') { // Same as above function, but paramters are in the opposite order
        $this->SetDBPassword($DBPassword, $DBUser);
    }

    function SetCustomPrimaryKey( $fieldname ) {
        $this->customPrimaryKey = $fieldname;
    }

    function SetNumberAutoConversionComma2PeriodForDecimal( ) {
        $this->useComma2Period = true;
        /* $this->fieldInfo[$i]['type']
    if( $this->fieldInfo[$i]['type'] == "NUMBER" && useComma2Period == true ){
        $this->fieldContent = str_replace( ',', '.', $this->fieldContent );
    }
*/
    }

    function SetDefaultOperator ($op) {
        $this->defaultOperator = $op;
        return true;
    }

// start of findquery section
    /**
    * Returns the "q" number (q1, q2, etc) if a duplicate name/value pair exists already, else returns a false value (q cannot be 0 anyway).
    *
    * @param mixed $name
    * @param mixed $value
    */
    function FindQuery_DuplicateExists($name, $value)
    {
        $currentParamList = $this->GetKeyPairDataParams();
        for($c = 1; $c <= $this->findquerynumber; $c++)
        {
            if (isset($currentParamList['-q'.$c]))
            {
                $cname = $currentParamList['-q'.$c];
                $cvalue = $currentParamList['-q'.$c.'.value'];
                if ($cname == $name && $cvalue == $value)
                {
                    return $c;
                }
            }
        }

        return false;
    }

    /**
    * when using FMFindQuery, appends name and value pairs to the findquery query string. optionally (doModify=false), returns the string for further manipulation.
    *  example:  $searchFields = array();
$searchFields[] = array('zAssignedGroup::ID_Group', $group);
$searchFields[] = array('DateClosed', $startdate.'...'.$enddate);
$wo_find->FindQuery_Append($searchFields);
    *   note the two arrays, to allow multiple of the same key in one find, to handle fields with multiple values separated by return.
    * @param mixed $namevaluepair
    * @param mixed $doModify
    */
    function FindQuery_Append($namevaluepair = array(), $doModify = true)
    {
        if (is_array($namevaluepair) && count($namevaluepair) > 0)
        {
            $appendquerystring = '';
            foreach($namevaluepair as $fieldInfo)  // fieldInfo is just an array with [0] as name, and [1] as data
            {

                    $foundFlagQNumber = $this->FindQuery_DuplicateExists($fieldInfo[0], $fieldInfo[1]);

                    if (!$foundFlagQNumber)
                    {
                        $this->AddDBParam('-q'.$this->findquerynumber, $fieldInfo[0]);
                        $this->AddDBParam('-q'.$this->findquerynumber.'.value', $fieldInfo[1]);

                        // add the find to the end of the string
                        $appendquerystring .= ',q'.$this->findquerynumber;

                        $this->findquerynumber++;
                    } else {
                        // the exact namevalue pair was found and attempted to be searched on again.
                       $appendquerystring .= ',q'.$foundFlagQNumber;
                    }

            }
            if ($appendquerystring != '')
            {
                $appendquerystring = substr($appendquerystring, 1); // strip beginning comma.
                if ($doModify)
                {
                    $this->findquerystring .= ';('.$appendquerystring . ')';
                } else {
                    return ';('.$appendquerystring . ')';
                }
            }
        }
    }

    /**
    * exact duplicate of FindQuery_Append except for the '!' near teh end..
    *
    * @param mixed $namevaluepair
    * @param mixed $doModify
    */
    function FindQuery_Omit($namevaluepair = array(), $doModify = true)
    {
        $str = $this->FindQuery_Append($namevaluepair, false); // send false to not modify the internal query string.
        if ($doModify)
        {
            // the string will come back looking like: ;(q1) so we have to strip the initial semicolon.
            $this->findquerystring .= ';!'.substr($str, 1);
        } else {
            return ';!'.substr($str, 1);
        }
    }

    function GetKeyPairDataParams()
    {
        $temp = array();
        foreach($this->dataParams as $key=>$row)
        {
            $name = $row['name'];
            $value = $row['value'];
            $temp[$name] = $value;
        }
        return $temp;
    }

    /**
    * Fields will be an array of fields you want to make an AND find on.
    * the second param will be the querystring to be used.
    * @param mixed $fields
    */
    function FindQuery_AND($namevaluepair = array(), $fieldnames = array(), $querystring = '', $doModify = true)
    {
        if ($querystring == '')
        {
            $querystring = $this->findquerystring; // use the internal querystring by default.
        }
        $qnumlist = array(); // used to keep a list of the qnum we are ANDing.
        if (is_array($namevaluepair) && count($namevaluepair) > 0)
        {

            foreach($namevaluepair as $fieldInfo)
            {

                    $qnum = $this->FindQuery_DuplicateExists($fieldInfo[0], $fieldInfo[1]);
                    if (!$qnum)
                    {
                        $this->FindQuery_Append(array(array($fieldInfo[0], $fieldInfo[1])), false); // add parameters to the list of possible query params but don't modify the query yet.
                        $qnum = $this->FindQuery_DuplicateExists($fieldInfo[0], $fieldInfo[1]); // find the q number after it has been created in the dataParams.
                    }
                    if ($qnum !== false) $qnumlist[] = $qnum;

            }

            if ($this->findquerystring == '') // if starting with an AND, then do this
            {
                foreach($qnumlist as $num)
                {
                    // make sure that the query data is not already in this section ex: (q2,q2) is illegal
                        $newquerystring .= ',q'.$num;
                }
                $newquerystring = ';('. substr($newquerystring, 1) .')'; // strip off initial comma
            } else {

                $findquerypieces = explode(';', $this->findquerystring);
                $newquerystring = '';
                foreach ($findquerypieces as $findquerypiece)
                {
                    if (!empty($findquerypiece))
                    {
                        $newquerystring .= ';'.substr($findquerypiece, 0, (strlen($findquerypiece)-1));
                        if (strpos($findquerypiece, '!') === false)
                        {
    //                        if (count($fieldnames) == 0 || in_array( /// check for field in dataParams for this query piece
                            foreach($qnumlist as $num)
                            {
                                // make sure that the query data is not already in this section ex: (q2,q2) is illegal
                                if (strpos($findquerypiece, 'q'.$num.')') == false && strpos($findquerypiece, 'q'.$num.',') === false)
                                {
                                    $newquerystring .= ',q'.$num;
                                }
                            }
                        }
                            $newquerystring .= ')';
                    }
//                     else {
//                        $newquerystring .= ';'.$findquerypiece;
//                    }
                }
            }
        }
        $this->findquerystring = $newquerystring;


    }
// end of findquery section

    function AddDBParam ($name, $value, $op="") {                        // Add a search parameter.  An operator is usually not necessary.
        if ($this->dataParamsEncoding != '' && function_exists('mb_convert_encoding')) {
            $this->dataParams[]["name"] = mb_convert_encoding($name, $this->dataParamsEncoding, $this->charSet);
            end($this->dataParams);
            $convedValue = mb_convert_encoding($value, $this->dataParamsEncoding, $this->charSet);
/* Masayuki Nii added at Oct 10, 2009 */
            if (!defined('SURROGATE_INPUT_PATCH_DISABLED') && $this->charSet == 'UTF-8' && $this->dataServerVersion < 12) {
                $count = 0;
                for ($i=0; $i< strlen($value); $i++) {
                    $c = ord(substr( $value, $i, 1 ));
                    if ( ( $c == 0xF0 )&&( (ord(substr( $value, $i+1, 1 )) & 0xF0) == 0xA0 )) {
                        $i += 4;    $count++;
                    }
                }
                $convedValue .= str_repeat( mb_convert_encoding(chr(0xE3).chr(0x80).chr(0x80), $this->dataParamsEncoding, 'UTF-8'), $count );
             }
            $this->dataParams[key($this->dataParams)]["value"] = $convedValue;
// =======================
        } else {
            $this->dataParams[]["name"] = $name;
            end($this->dataParams);
            $this->dataParams[key($this->dataParams)]["value"] = $value;
        }
        $this->dataParams[key($this->dataParams)]["op"] = $op;
    }

    function AddDBParamArray ($paramsArray, $paramOperatorsArray=array()) { // Add an array of search parameters.  An operator is usually not necessary.
        foreach ($paramsArray as $key => $value) {
            if (isset($paramOperatorsArray[$key]) && strlen(trim($paramOperatorsArray[$key])) > 0) {
                $this->AddDBParam($key, $value, $paramOperatorsArray[$key]);
            } else {
                $this->AddDBParam($key, $value);
            }
        }
    }

    function SetPortalRow ($fieldsArray, $portalRowID=0, $relationshipName='') {
        foreach ($fieldsArray as $fieldName => $fieldValue) {
            if (strlen(trim($relationshipName)) > 0 && substr_count($fieldName, '::') < 1) {
                $this->AddDBParam("{$relationshipName}::{$fieldName}.{$portalRowID}", $fieldValue);
            } else {
                $this->AddDBParam("{$fieldName}.{$portalRowID}", $fieldValue);
            }
        }
    }

    function SetRecordID ($recordID) {
        if (! is_numeric($recordID) || (intval($recordID) != $recordID)) {
            if ((defined("DEBUG") and DEBUG) or DEBUG_FUZZY) {
                $currentDebugString = "<p>RecordIDs must be integers.  Value passed was &quot;{$recordID}&quot;.</p>\n";
                $this->lastDebugMessage .= $currentDebugString;
                if (defined("DEBUG") and DEBUG) {
                    echo $currentDebugString;
                }
            }
        }
        $this->AddDBParam('-recid', $recordID);
    }

    function SetModID ($modID) {
        if (! is_numeric($modID) || (intval($modID) != $modID)) {
            if ((defined("DEBUG") and DEBUG) or DEBUG_FUZZY) {
                $currentDebugString = "<p>ModIDs must be integers.  Value passed was &quot;{$modID}&quot;.</p>\n";
                $this->lastDebugMessage .= $currentDebugString;
                if (defined("DEBUG") and DEBUG) {
                    echo $currentDebugString;
                }
            }
        }
        $this->AddDBParam('-modid', $modID);
    }

    function SetLogicalOR () {
        $this->AddDBParam('-lop', 'or');
    }

    // FileMaker 7 only
    function SetFMGlobal ($globalFieldName, $globalFieldValue) {
        $this->AddDBParam("{$globalFieldName}.global", $globalFieldValue);
    }

    function PerformFMScript ($scriptName) {                            // This function is only meaningful when working with FileMaker data sources
        $this->AddDBParam('-script', $scriptName);
    }

    function PerformFMScriptPrefind ($scriptName) {                     // This function is only meaningful when working with FileMaker data sources
        $this->AddDBParam('-script.prefind', $scriptName);
    }

    function PerformFMScriptPresort ($scriptName) {                     // This function is only meaningful when working with FileMaker data sources
        $this->AddDBParam('-script.presort', $scriptName);
    }

    function AddSortParam ($field, $sortOrder='', $performOrder=0) {    // Add a sort parameter.  An operator is usually not necessary.
        if ($performOrder > 0) {
            $this->sortParams[$performOrder]["field"] = $field;
            $this->sortParams[$performOrder]["sortOrder"] = $sortOrder;
        } else {
            if (count($this->sortParams) == 0) {
                $this->sortParams[1]["field"] = $field;
            } else {
                $this->sortParams[]["field"] = $field;
            }
            end($this->sortParams);
            $this->sortParams[key($this->sortParams)]["sortOrder"] = $sortOrder;
        }
    }

    function FMSkipRecords ($skipSize) {
        $this->currentSkip = $skipSize;
    }

    function FMPostQuery ($isPostQuery = true) {
        $this->isPostQuery = $isPostQuery;
    }

    function FMFOpenQuery ($isFOpenQuery = true) {
        $this->isFOpenQuery = $isFOpenQuery;
    }

    function FMUseCURL ($useCURL = true) {
        $this->useCURL = $useCURL;
    }

    // By default, FX.php adds an extra layer to the returned array to allow for repeating fields and portals.
    // When these are not present, or when accessing SQL data, this may not be desirable.  FlattenInnerArray() removes this extra layer.
    function FlattenInnerArray () {
        $this->useInnerArray = false;
    }

    // This will give you the fields and contents pr record as JSON
    function ReturnJSON () {
        $this->useReturnJSONResult = false;
    }

    // This will give you the whole FMPXMLRESULT as JSON
    function ReturnJSONFullArray () {
        $this->useReturnJSONFullArrayResult = false;
    }

/* The actions that you can send to FileMaker start here */

    function FMDBOpen () {
        $queryResult = $this->ExecuteQuery("-dbopen");
        if (FX::isError($queryResult)){
            return $queryResult;
        }
    }

    function FMDBClose () {
        $queryResult = $this->ExecuteQuery("-dbclose");
        if (FX::isError($queryResult)){
            return $queryResult;
        }
    }

    function FMDelete ($returnDataSet = false, $returnData = 'basic', $useInnerArray = true) {
        return $this->FMAction("-delete", $returnDataSet, $returnData, $useInnerArray);
    }

    function FMDup ($returnDataSet = true, $returnData = 'full', $useInnerArray = true) {
        return $this->FMAction("-dup", $returnDataSet, $returnData, $useInnerArray);
    }

    function FMEdit ($returnDataSet = true, $returnData = 'full', $useInnerArray = true) {
        return $this->FMAction("-edit", $returnDataSet, $returnData, $useInnerArray);
    }

    function FMFind ($returnDataSet = true, $returnData = 'full', $useInnerArray = true) {
        return $this->FMAction("-find", $returnDataSet, $returnData, $useInnerArray);
    }

    function FMFindAll ($returnDataSet = true, $returnData = 'full', $useInnerArray = true) {
        return $this->FMAction("-findall", $returnDataSet, $returnData, $useInnerArray);
    }

    function FMFindAny ($returnDataSet = true, $returnData = 'full', $useInnerArray = true) {
        return $this->FMAction("-findany", $returnDataSet, $returnData, $useInnerArray);
    }

    function FMFindQuery ($returnDataSet = true, $returnData = 'full', $useInnerArray = true)
    {
            return $this->FMAction("-findquery", $returnDataSet, $returnData, $useInnerArray);
    }

    function FMNew ($returnDataSet = true, $returnData = 'full', $useInnerArray = true) {
        return $this->FMAction("-new", $returnDataSet, $returnData, $useInnerArray);
    }

    function FMView ($returnDataSet = true, $returnData = 'full', $useInnerArray = true) {
        return $this->FMAction("-view", $returnDataSet, $returnData, $useInnerArray);
    }

    function FMDBNames ($returnDataSet = true, $returnData = 'full', $useInnerArray = true) {
        return $this->FMAction("-dbnames", $returnDataSet, $returnData, $useInnerArray);
    }

    function FMLayoutNames ($returnDataSet = true, $returnData = 'full', $useInnerArray = true) {
        return $this->FMAction("-layoutnames", $returnDataSet, $returnData, $useInnerArray);
    }

    function FMScriptNames ($returnDataSet = true, $returnData = 'full', $useInnerArray = true) {
        return $this->FMAction("-scriptnames", $returnDataSet, $returnData, $useInnerArray);
    }

    // DoFXAction() is a general purpose action function designed to streamline FX.php code
    function DoFXAction ($currentAction, $returnDataSet = true, $useInnerArray = false, $returnType = 'object') {
        return $this->FMAction($currentAction, $returnDataSet, $returnType, $useInnerArray);
    }

/* The actions that you can send to FileMaker end here */
    // PerformSQLQuery() is akin to the FileMaker actions above with two differences:
    //  1) It is SQL specific
    //  2) The SQL query passed is the sole determinant of the query performed (AddDBParam, etc. will be ignored)
    function PerformSQLQuery ($SQLQuery, $returnDataSet = true, $useInnerArray = false, $returnData = 'object') {
        $this->dataQuery = $SQLQuery;
        return $this->FMAction("-sqlquery", $returnDataSet, $returnData, $useInnerArray);
    }

    // SetDataKey() is used for SQL queries as a way to provide parity with the RecordID/ModID combo provided by FileMaker Pro
    function SetDataKey ($keyField, $modifyField = '', $separator = '.') {
        $this->primaryKeyField = $keyField;
        $this->modifyDateField = $modifyField;
        $this->dataKeySeparator = $separator;
        return true;
    }

    // SetSelectColumns() allows users to specify which columns should be returned by an SQL SELECT statement
    function SetSelectColumns ($columnList) {
        $this->selectColsSet = true;
        $this->selectColumns = $columnList;
        return true;
    }

    // SQLFuzzyKeyLogicOn() can be used to have FX.php make it's best guess as to a viable key in an SQL DB
    function SQLFuzzyKeyLogicOn ($logicSwitch = false) {
        $this->fuzzyKeyLogic = $logicSwitch;
        return true;
    }

    // By default, FX.php uses records' keys as the indices for the returned array.  UseGenericKeys() is used to change this behavior.
    function UseGenericKeys ($genericKeys=true) {
        $this->genericKeys = $genericKeys;
        return true;
    }

    // Added by Masayuki Nii(nii@msyk.net) Dec 18, 2010
    // Modified by msyk, Feb 1-6, 2012
    function RemainAsArray (
                $rArray1,$rArray2=NULL,$rArray3=NULL,$rArray4=NULL,$rArray5=NULL,
                $rArray6=NULL,$rArray7=NULL,$rArray8=NULL,$rArray9=NULL,$rArray10=NULL,
                $rArray11=NULL,$rArray12=NULL) {
        $this->portalAsRecord = false;
        $counter = 0;
        for ( $i=1 ; $i<13 ; $i++ ) {
            $valName = "rArray{$i}";
             if ( ! isset($$valName) ) {
                break;
            }
            if (is_array($$valName)) {
                $this->portalAsRecord = true;
                $isFirstTime = true;
                $firstItemName = '';
                foreach($$valName as $item) {
                    if($isFirstTime)    {
                        $isFirstTime = false;
                        $firstItemName = $item;
                        $this->remainNamesReverse[$item] = true;
                    } else {
                        $this->remainNamesReverse[$item] = $firstItemName;
                    }
                    $this->remainNames[$counter] = $item;
                    $counter++;
                }    
            } else {
                $this->remainNames[$counter] = $$valName;
                $this->remainNamesReverse[$$valName] = true;
                $counter++;
            }
        }
    }
}
?>
