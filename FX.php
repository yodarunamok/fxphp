<?php
#### FX.php #############################################################
#                                                                       #
#       By: Chris Hansen with Chris Adams, G G Thorsen, and others      #
#  Version: 4.5.1                                                       #
#     Date: 28 Feb 2008                                                 #
#  License: Artistic License and addendum (included with release)       #
# Web Site: www.iviking.org                                             #
#  Details: FX is a free open-source PHP class for accessing FileMaker  #
#          and other databases.  For complete details about this class, #
#          please visit www.iviking.org.                                #
#                                                                       #
#########################################################################

define("FX_VERSION", '4.5.1');                                            // Current version information for FX.php.  New constants as of version 4.0.
define("FX_VERSION_FULL", "FX.php version 4.5.1 (28 Feb 2008) by Chris Hansen, Chris Adams, G G Thorsen, and others.");

require_once('FX_Error.php');                                           // This version of FX.php includes object based error handling.  See
                                                                        // FX_Error.php for more information.

if (! defined('DEBUG_FUZZY')) {                                         // This version of FX.php includes the FX Fuzzy Debugger (turned off by default.)
    define('DEBUG_FUZZY', false);
}

require_once('FX_constants.php');                                       // The constants in this file are designed to be used with DoFXAction()

define("EMAIL_ERROR_MESSAGES", FALSE);                                  // Set this to TRUE to enable emailing of specific error messages.
define("DISPLAY_ERROR_MESSAGES", TRUE);                                 // Set this to FALSE to display the $altErrorMessage to the user.
$webmasterEmailAddress = 'webmaster@yourdomain.com';                    // If you set the above to TRUE, enter the appropriate email address on this line.
$emailFromAddress = 'you@yourdomain.com';                               // Sets who the error message will show as the sender.

function EmailError ($errorText)
{
    global $webmasterEmailAddress;
    global $emailFromAddress;

    if (EMAIL_ERROR_MESSAGES) {
        $emailSubject = "PHP Server Error";
        $emailMessage = "The following error just occured:\r\n\r\nMessage: {$errorText}\r\n\r\n**This is an automated message**";
        $emailStatus = mail($webmasterEmailAddress, $emailSubject, $emailMessage, "From: $emailFromAddress\r\n");
    }
}

function EmailErrorHandler ($FXErrorObj)
{
    $altErrorMessage = 'The Server was unable to process your request.<br />The WebMaster has been emailed.<br /> Thank you for your patience.';

    EmailError($FXErrorObj->message);
    if (DISPLAY_ERROR_MESSAGES) {
        echo($FXErrorObj->message);
    } else {
        echo($altErrorMessage);
    }
    return true;
}

class FX
{
    // These are the basic database variables.
    var $dataServer = "";
    var $dataServerType = 'FMPro7';
    var $dataPort;
    var $dataPortSuffix;
    var $urlScheme;
    var $useSSLProtocol = false;
    var $database = "";
    var $layout = ""; // the layout to be accessed for FM databases.  For SQL, the table to be accessed.
    var $responseLayout = "";
    var $groupSize;
    var $currentSkip = 0;
    var $defaultOperator = 'bw';
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
    var $dataURL = "";
    var $dataURLParams = "";
    var $dataQuery = "";

    // Variables used to track how data is moved in and out of FileMaker.  Used when UTF-8 just doesn't cut it (as when working with Japanese characters.)
    // This and all related code were submitted by Masayuki Nii.
    // Note that if either of these variables are simply empty, UTF-8 is the default.
    var $charSet = '';                                                  // Determines how outgoing data is encoded.
    var $dataParamsEncoding = '';                                       // Determines how incoming data is encoded.

    var $remainNames = array();	// Added by Masayuki Nii(nii@msyk.net) Dec 18, 2010
    var $remainNamesReverse = array();	// Added by Masayuki Nii(nii@msyk.net) Jan 23, 2010
    var $portalAsRecord =false;	// Added by Masayuki Nii(nii@msyk.net) Dec 18, 2010
    var $currentSubrecordIndex;	// Added by Masayuki Nii(nii@msyk.net) Dec 18, 2010
    var $forceFlatten = false;	// Added by Masayuki Nii(nii@msyk.net) Feb 1, 2012

    // Flags and Error Tracking
    var $currentFlag = '';
    var $currentRecord = '';
    var $currentField = '';
    var $currentValueList = '';
    var $fieldCount = 0;
    var $columnCount = -1;                                                // columnCount is ++ed BEFORE looping
    var $fxError = 'No Action Taken';
    var $errorTracking = 0;
    var $useInnerArray = true;                                              // Do NOT change this variable directly.  Use FlattenInnerArray() or the appropriate param of action method.
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
    var $invalidXMLChars = array("\x0B", "\x0C", "\x12");
    var $fuzzyFXPass = ''; // this is to handle the fact that I couldn't provide a default value for a pass-by-value param in PHP4

    /*
        Translation arrays used with str_replace to handle special
        characters in UTF-8 data received from FileMaker. The two arrays
        should have matching numeric indexes such that $UTF8SpecialChars[0]
        contains the raw binary equivalent of $UTF8HTMLEntities[0].

        This would be a perfect use for strtr(), except that it only works
        with single-byte data. Instead, we use preg_replace, which means
        that we need to delimit our match strings

        Please note that in this latest release I've removed the need for
        the include files which contained long lists of characters. Gjermund
        was sure there was a better way and he was right. With the two six
        element arrays below, every unicode character is allowed for. Let
        me know how this works for you. A link to Gjermund's homepage can
        be found in the FX Links section of www.iViking.org.
     */
    var $UTF8SpecialChars = array(
        "|([\xC2-\xDF])([\x80-\xBF])|e",
        "|(\xE0)([\xA0-\xBF])([\x80-\xBF])|e",
        "|([\xE1-\xEF])([\x80-\xBF])([\x80-\xBF])|e",
        "|(\xF0)([\x90-\xBF])([\x80-\xBF])([\x80-\xBF])|e",
        "|([\xF1-\xF3])([\x80-\xBF])([\x80-\xBF])([\x80-\xBF])|e",
        "|(\xF4)([\x80-\x8F])([\x80-\xBF])([\x80-\xBF])|e"
    );

    var $UTF8HTMLEntities = array(
        "\$this->BuildExtendedChar('\\1','\\2')",
        "\$this->BuildExtendedChar('\\1','\\2','\\3')",
        "\$this->BuildExtendedChar('\\1','\\2','\\3')",
        "\$this->BuildExtendedChar('\\1','\\2','\\3','\\4')",
        "\$this->BuildExtendedChar('\\1','\\2','\\3','\\4')",
        "\$this->BuildExtendedChar('\\1','\\2','\\3','\\4')"
    );

    function BuildExtendedChar ($byteOne, $byteTwo="\x00", $byteThree="\x00", $byteFour="\x00")
    {
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

    function ClearAllParams ()
    {
        $this->userPass = "";
        $this->dataURL = "";
        $this->dataURLParams = "";
        $this->dataQuery = "";
        $this->dataParams = array();
        $this->sortParams = array();
        $this->fieldInfo = array();
        $this->valueLists = array();
        $this->fieldCount = 0;
        $this->currentSkip = 0;
        $this->currentData = array();
        $this->columnCount = -1;
        $this->currentRecord = "";
        $this->currentField = "";
        $this->currentFlag = "";
        $this->isPostQuery = $this->defaultPostPolicy;
        $this->isFOpenQuery = $this->defaultFOpenPolicy;
        $this->primaryKeyField = '';
        $this->modifyDateField = '';
        $this->dataKeySeparator = '';
        $this->fuzzyKeyLogic = false;
        $this->genericKeys = false;
        $this->useInnerArray = true;
        $this->remainNames = array();	// Added by Masayuki Nii(nii@msyk.net) Dec 18, 2010
        $this->remainNamesReverse = array();	// Added by Masayuki Nii(nii@msyk.net) Jan 23, 2011
        $this->portalAsRecord = false;	// Added by Masayuki Nii(nii@msyk.net) Dec 18, 2010
    }

    function ErrorHandler ($errorText)
    {
        $this->fxError = $errorText;
        $this->errorTracking = 3300;
        return $errorText;
    }

    function FX ($dataServer, $dataPort=80, $dataType='', $dataURLType='')
    {
        $this->dataServer = $dataServer;
        $this->dataPort = $dataPort;
        $this->dataPortSuffix = ":" . $dataPort;
        if (strlen($dataType) > 0) {
            $this->dataServerType = $dataType;
        }
        $dataTypeSmallCapital = strtolower($dataType);
        if (strlen($dataURLType) > 0
            &&
            (   $dataTypeSmallCapital == 'fmpro7' || $dataTypeSmallCapital == 'fmpro8' ||
                $dataTypeSmallCapital == 'fmpro9' || $dataTypeSmallCapital == 'fmpro10' ||
                $dataTypeSmallCapital == 'fmpro11'  )
            &&
            strtolower($dataURLType) == 'https') {

            $this->useSSLProtocol = true;
            $this->urlScheme = 'https';
        } else {
            $this->useSSLProtocol = false;
            $this->urlScheme = 'http';
        }

        $this->ClearAllParams();
        $this->lastDebugMessage = '<p>Instantiating FX.php.</p>';
    }

    function CreateCurrentSort ()
    {
        $currentSort = "";

        foreach ($this->sortParams as $key1 => $value1) {
            $field = '';
            $sortOrder = '';    // prevent to report error in IDE. (msyk, Feb 1, 2012)
            foreach ($value1 as $key2 => $value2) {
                $$key2 = $value2;
            }
            $lowerCaseDataServerType = strtolower($this->dataServerType);
            if (substr($lowerCaseDataServerType, 0, 5) == 'fmpro' && substr($lowerCaseDataServerType, -1) > 6) {
                if ($sortOrder == "") {
                    $currentSort .= "&-sortfield.{$key1}=" . str_replace ("%3A%3A", "::", rawurlencode($field));
                }
                else {
                    $currentSort .= "&-sortfield.{$key1}=" . str_replace ("%3A%3A", "::", rawurlencode($field)) . "&-sortorder.{$key1}=" . $sortOrder;
                }
            } else {
                if ($sortOrder == "") {
                    $currentSort .= "&-sortfield=" . str_replace ("%3A%3A", "::", rawurlencode($field));
                }
                else {
                    $currentSort .= "&-sortfield=" . str_replace ("%3A%3A", "::", rawurlencode($field)) . "&-sortorder=" . $sortOrder;
                }
            }
        }
        return $currentSort;
    }

    function CreateCurrentSearch ()
    {
        $currentSearch = '';

        foreach ($this->dataParams as $key1 => $value1) {
            $name = '';
            $value = '';
            $op = '';   // prevent to report error in IDE. (msyk, Feb 1, 2012)
            foreach ($value1 as $key2 => $value2) {
                $$key2 = $value2;
            }
            if ($op == "" && $this->defaultOperator == 'bw') {
                $currentSearch .= "&" . str_replace ("%3A%3A", "::", urlencode($name)) . "=" . urlencode($value);
            } else {
                if ($op == "") {
                    $op = $this->defaultOperator;
                }
                switch (strtolower($this->dataServerType)) {
                    case 'fmpro5':
                    case 'fmpro6':
                    case 'fmpro5/6':
                        $currentSearch .= "&-op=" . $op . "&" . str_replace("%3A%3A", "::", urlencode($name)) . "=" . urlencode($value);
                        break;
                    case 'fmpro7':
                    case 'fmpro8':
                    case 'fmpro9':
                    case 'fmpro10':
                    case 'fmpro11':
                        $tempFieldName = str_replace("%3A%3A", "::", urlencode($name));
                        $currentSearch .= "&" . $tempFieldName . ".op=" . $op . "&" . $tempFieldName . "=" . urlencode($value);
                        break;
                }
            }
        }
        return $currentSearch;
    }

    function AssembleCurrentSearch ($layRequest, $skipRequest, $currentSort, $currentSearch, $action, $FMV=6)
    {
        $tempSearch = '';

        $tempSearch = "-db=" . urlencode($this->database);               // add the name of the database...
        $tempSearch .= $layRequest;                                      // and any layout specified...
        if ($FMV < 7) {
            $tempSearch .= "&-format=-fmp_xml";                          // then set the FileMaker XML format to use...
        }
        $tempSearch .= "&-max=$this->groupSize$skipRequest";             // add the set size and skip size data...
        $tempSearch .= $currentSort . $currentSearch . "&" . $action;    // finally, add sorting, search parameters, and action data.
        return $tempSearch;
    }

    function StartElement($parser, $name, $attrs)                        // The functions to start XML parsing begin here
    {
        switch(strtolower($name)) {
             case "data":
                $this->currentFlag = "parseData";
                if ($this->useInnerArray) {
                	$this->currentData[$this->currentRecord][$this->currentField][$this->currentFieldIndex] = "";
                } else {
                	if ($this->isRemaiName($this->currentField))	{
                		if ( $this->portalAsRecord )	{
                			$this->currentData[$this->currentRecord][$this->getTOCName($this->currentField)][$this->currentSubrecordIndex][$this->currentField] = '';               			
                		} else {
                			$this->currentData[$this->currentRecord][$this->currentField][$this->currentFieldIndex] = "";
                		}
                	} else {
	                    $this->currentData[$this->currentRecord][$this->currentField] = "";
                	}
                }
                break;
            case "col":
            	$this->currentFieldIndex = 0;
                ++$this->columnCount;
                $this->currentField = $this->fieldInfo[$this->columnCount]['name'];
                if ($this->useInnerArray) {
                    $this->currentData[$this->currentRecord][$this->currentField] = array();
                } else if ($this->isRemaiName($this->currentField))	{
                	if ( $this->portalAsRecord )	{
                		$this->currentSubrecordIndex = 0;
                	} else {
                		$this->currentData[$this->currentRecord][$this->currentField] = array();
                	}
                }
                break;
            case "row":
                $recordid = '';    // prevent to report error in IDE. (msyk, Feb 1, 2012)
                foreach ($attrs as $key => $value) {
                    $key = strtolower($key);
                    $$key = $value;
                }
                if (substr_count($this->dataURL, '-dbnames') > 0 || substr_count($this->dataURL, '-layoutnames') > 0) {
                    $modid = count($this->currentData);
                }
                $this->currentRecord = $recordid . '.' . $modid;
                $this->currentData[$this->currentRecord] = array( '-recid' => $recordid, '-modid' => $modid );
                break;
            case "field":
                if ($this->charSet  != '' && defined('MB_OVERLOAD_STRING')) {
                    foreach ($attrs as $key => $value) {
                        $key = strtolower($key);
                        $this->fieldInfo[$this->fieldCount][$key] = mb_convert_encoding($value, $this->charSet, 'UTF-8');
                    }
                } else {
                    foreach ($attrs as $key => $value) {
                        $key = strtolower($key);
                        $this->fieldInfo[$this->fieldCount][$key] = $value;
                    }
                }
                $this->fieldInfo[$this->fieldCount]['extra'] = ''; // for compatibility w/ SQL databases
                if (substr_count($this->dataURL, '-view') < 1) {
                    $this->fieldCount++;
                }
                break;
            case "style":
                foreach ($attrs as $key => $value) {
                    $key = strtolower($key);
                    $this->fieldInfo[$this->fieldCount][$key] = $value;
                }
                break;
            case "resultset":
                foreach ($attrs as $key => $value) {
                    switch(strtolower($key)) {
                        case "found":
                          $this->foundCount = (int)$value;
                          break;
                    }
                }
                break;
            case "errorcode":
                $this->currentFlag = "fmError";
                break;
            case "valuelist":
                foreach ($attrs as $key => $value) {
                    if (strtolower($key) == "name") {
                        $this->currentValueList = $value;
                    }
                }
                $this->valueLists[$this->currentValueList] = array();
                $this->currentFlag = "values";
                $this->currentValueListElement = -1;
                break;
            case "value":
                $this->currentValueListElement++;
                $this->valueLists[$this->currentValueList][$this->currentValueListElement] = "";
                break;
            case "database":
                foreach ($attrs as $key => $value) {
                    switch(strtolower($key)) {
                        case "dateformat":
                          $this->dateFormat = $value;
                          break;
                        case "records":
                          $this->totalRecordCount = $value;
                          break;
                        case "timeformat":
                          $this->timeFormat = $value;
                          break;
                    }
                }
                break;
            default:
                break;
        }
    }

    function ElementContents($parser, $data)
    {
        switch($this->currentFlag) {
            case "parseData":
                if ($this->dataParamsEncoding  != '' && defined('MB_OVERLOAD_STRING')) {
                    if ($this->useInnerArray) {
                        $this->currentData[$this->currentRecord][$this->currentField][$this->currentFieldIndex] .= mb_convert_encoding($data, $this->charSet, 'UTF-8');
                    } else {
                    	if ($this->isRemaiName($this->currentField))	{
                    		if ( $this->portalAsRecord )	{
                    			$this->currentData[$this->currentRecord][$this->getTOCName($this->currentField)][$this->currentSubrecordIndex][$this->currentField] .= mb_convert_encoding($data, $this->charSet, 'UTF-8');
                			} else {
                				$this->currentData[$this->currentRecord][$this->currentField][$this->currentFieldIndex] .= mb_convert_encoding($data, $this->charSet, 'UTF-8');
                			}
                    	} else {
                        	$this->currentData[$this->currentRecord][$this->currentField] .= mb_convert_encoding($data, $this->charSet, 'UTF-8');
                    	}
                    }
                } else {
                    if ($this->useInnerArray) {
                        $this->currentData[$this->currentRecord][$this->currentField][$this->currentFieldIndex] .= preg_replace($this->UTF8SpecialChars, $this->UTF8HTMLEntities, $data);
                    } else {
                    	if ($this->isRemaiName($this->currentField))	{
                    		if ( $this->portalAsRecord )	{
                				$this->currentData[$this->currentRecord][$this->getTOCName($this->currentField)][$this->currentSubrecordIndex][$this->currentField] .= preg_replace($this->UTF8SpecialChars, $this->UTF8HTMLEntities, $data);
                   			} else {
                    			$this->currentData[$this->currentRecord][$this->currentField][$this->currentFieldIndex] .= preg_replace($this->UTF8SpecialChars, $this->UTF8HTMLEntities, $data);
                			}
                    	} else {
                    		$this->currentData[$this->currentRecord][$this->currentField] .= preg_replace($this->UTF8SpecialChars, $this->UTF8HTMLEntities, $data);
                    	}
                    }
                }
                break;
            case "fmError":
                $this->fxError = $data;
                break;
            case "values":
                $this->valueLists[$this->currentValueList][$this->currentValueListElement] .= preg_replace($this->UTF8SpecialChars, $this->UTF8HTMLEntities, $data);
                break;
        }
    }

    function EndElement($parser, $name)
    {
        switch(strtolower($name)) {
            case "data":
                $this->currentFieldIndex++;
                $this->currentFlag = "";
                $this->currentSubrecordIndex++;
                break;
            case "col":
            	break;
            case "row":
                if( strlen( trim( $this->customPrimaryKey ) ) > 0 ) {
                    if( $this->useInnerArray ) {
						$this->currentData[$this->currentData[$this->currentRecord][$this->customPrimaryKey][0]] 
							= $this->currentData[$this->currentRecord];
                    } else {
                    	if ($this->isRemaiName($this->currentField))	{
                    		if ( $this->portalAsRecord )	{
                				//
                			} else {
                    			$this->currentData[$this->currentData[$this->currentRecord][$this->customPrimaryKey][0]] 
									= $this->currentData[$this->currentRecord];
                			}
                    	} else {
							$this->currentData[$this->currentData[$this->currentRecord][$this->customPrimaryKey]] 
								= $this->currentData[$this->currentRecord];
                    	}
                    }
                    unset($this->currentData[$this->currentRecord]);
                }
                $this->columnCount = -1;
                break;
            case "field":
                if (substr_count($this->dataURL, '-view') > 0) {
                    $this->fieldCount++;
                }
                break;
            case "errorcode":
            case "valuelist":
                $this->currentFlag = "";
                break;
        }
    }                                                                       // XML Parsing Functions End Here

    function RetrieveFMData ($action)
    {
        $data = '';
        if ($this->DBPassword != '') {                                      // Assemble the Password Data
            $this->userPass = $this->DBUser . ':' . $this->DBPassword . '@';
        }
        if ($this->layout != "") {                                          // Set up the layout portion of the query.
            $layRequest = "&-lay=" . urlencode($this->layout);
        }
        else {
            $layRequest = "";
        }
        if ($this->currentSkip > 0) {                                       // Set up the skip size portion of the query.
            $skipRequest = "&-skip=$this->currentSkip";
        } else {
            $skipRequest = "";
        }
        $currentSort = $this->CreateCurrentSort();
        $currentSearch = $this->CreateCurrentSearch();
        $this->dataURL = "http://{$this->userPass}{$this->dataServer}{$this->dataPortSuffix}/FMPro"; // First add the server info to the URL...
        $this->dataURLParams = $this->AssembleCurrentSearch($layRequest, $skipRequest, $currentSort, $currentSearch, $action);
        $this->dataURL .= '?' . $this->dataURLParams;

        if ((defined("DEBUG") and DEBUG) or DEBUG_FUZZY) {
            $currentDebugString = "<p>Using FileMaker URL: <a href=\"{$this->dataURL}\">{$this->dataURL}</a></p>\n";
            $this->lastDebugMessage .= $currentDebugString;
            if (defined("DEBUG") and DEBUG) {
                echo $currentDebugString;
            }
        }

        if (defined("HAS_PHPCACHE") and defined("FX_USE_PHPCACHE") and strlen($this->dataURLParams) <= 510 and (substr_count($this->dataURLParams, '-find') > 0 || substr_count($this->dataURLParams, '-view') > 0 || substr_count($this->dataURLParams, '-dbnames') > 0 || substr_count($this->dataURLParams, '-layoutnames') > 0)) {
            $data = get_url_cached($this->dataURL);
            if (! $data) {
                return new FX_Error("Failed to retrieve cached URL in RetrieveFMData()");
            }
            $data = $data["Body"];
        } elseif ($this->isPostQuery) {
            if ($this->useCURL && defined("CURLOPT_TIMEVALUE")) {
                $curlHandle = curl_init(str_replace($this->dataURLParams, '', $this->dataURL));
                curl_setopt($curlHandle, CURLOPT_POST, 1);
                curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $this->dataURLParams);
                ob_start();
                if (! curl_exec($curlHandle)) {
                    $this->lastDebugMessage .= "<p>Unable to connect to FileMaker.  Use the DEBUG constant and try connecting with the resulting URL manually.<br />\n";
                    $this->lastDebugMessage .= "You should also double check the user name and password used, the server address, and Web Companion configuration.</p>\n";
                    return new FX_Error("cURL could not retrieve Post data in RetrieveFMData(). A bad URL is the most likely reason.");
                }
                curl_close($curlHandle);
                $data = trim(ob_get_contents());
                ob_end_clean();
                if (substr($data, -1) != '>') {
                    $data = substr($data, 0, -1);
                }
            } else {
                $dataDelimiter = "\r\n";
                $socketData = "POST /FMPro HTTP/1.0{$dataDelimiter}";
                if (strlen(trim($this->userPass)) > 1) {
                    $socketData .= "Authorization: Basic " . base64_encode($this->DBUser . ':' . $this->DBPassword) . $dataDelimiter;
                }
                $socketData .= "Host: {$this->dataServer}:{$this->dataPort}{$dataDelimiter}";
                $socketData .= "Pragma: no-cache{$dataDelimiter}";
                $socketData .= "Content-length: " . strlen($this->dataURLParams) . $dataDelimiter;
                $socketData .= "Content-type: application/x-www-form-urlencoded{$dataDelimiter}";
                // $socketData .= "Connection: close{$dataDelimiter}";
                $socketData .= $dataDelimiter . $this->dataURLParams;

                $fp = fsockopen ($this->dataServer, $this->dataPort, $this->errorTracking, $this->fxError, 30);
                if (! $fp) {
                    $this->lastDebugMessage .= "<p>Unable to connect to FileMaker.  Use the DEBUG constant and try connecting with the resulting URL manually.<br />\n";
                    $this->lastDebugMessage .= "You should also double check the user name and password used, the server address, and Web Companion configuration.</p>\n";
                    return new FX_Error( "Could not fsockopen the URL in retrieveFMData" );
                }
                fputs ($fp, $socketData);
                while (!feof($fp)) {
                    $data .= fgets($fp, 128);
                }
                fclose($fp);
                $pos = strpos($data, chr(13) . chr(10) . chr(13) . chr(10)); // the separation code
                $data = substr($data, $pos + 4) . "\r\n";
            }
        } else {
            $fp = fopen($this->dataURL, "r");
            if (! $fp) {
                $this->lastDebugMessage .= "<p>Unable to connect to FileMaker.  Use the DEBUG constant and try connecting with the resulting URL manually.<br />\n";
                $this->lastDebugMessage .= "You should also double check the user name and password used, the server address, and Web Companion configuration.</p>\n";
                return new FX_Error("Could not fopen URL in RetrieveFMData.");
            }
            while (!feof($fp)) {
                $data .= fread($fp, 4096);
            }
            fclose($fp);
        }
        $data = str_replace($this->invalidXMLChars, '', $data);
        return $data;
    }

    function RetrieveFM7Data ($action)
    {
        $data = '';
        if ($this->DBPassword != '' || $this->DBUser != 'FX') {             // Assemble the Password Data
            $this->userPass = $this->DBUser . ':' . $this->DBPassword . '@';
        }
        if ($this->layout != "") {                                          // Set up the layout portion of the query.
            $layRequest = "&-lay=" . urlencode($this->layout);
            if ($this->responseLayout != "") {
                $layRequest .= "&-lay.response=" . urlencode($this->responseLayout);
            }
        }
        else {
            $layRequest = "";
        }
        if ($this->currentSkip > 0) {                                       // Set up the skip size portion of the query.
            $skipRequest = "&-skip={$this->currentSkip}";
        } else {
            $skipRequest = "";
        }
        $currentSort = $this->CreateCurrentSort();
        $currentSearch = $this->CreateCurrentSearch();
        if ($action == '-view') {
            $FMFile = 'FMPXMLLAYOUT.xml';
        } else {
            $FMFile = 'FMPXMLRESULT.xml';
        }
        $this->dataURL = "{$this->urlScheme}://{$this->userPass}{$this->dataServer}{$this->dataPortSuffix}/fmi/xml/{$FMFile}"; // First add the server info to the URL...
        $this->dataURLParams = $this->AssembleCurrentSearch($layRequest, $skipRequest, $currentSort, $currentSearch, $action, 7);
        $this->dataURL .= '?' . $this->dataURLParams;

        if ((defined("DEBUG") and DEBUG) or DEBUG_FUZZY) {
            $currentDebugString = "<p>Using FileMaker URL: <a href=\"{$this->dataURL}\">{$this->dataURL}</a></p>\n";
            $this->lastDebugMessage .= $currentDebugString;
            if (defined("DEBUG") and DEBUG) {
                echo $currentDebugString;
            }
        }

        if (defined("HAS_PHPCACHE") and defined("FX_USE_PHPCACHE") and strlen($this->dataURLParams) <= 510 and (substr_count($this->dataURLParams, '-find') > 0 || substr_count($this->dataURLParams, '-view') > 0 || substr_count($this->dataURLParams, '-dbnames') > 0 || substr_count($this->dataURLParams, '-layoutnames') > 0)) {
            $data = get_url_cached($this->dataURL);
            if (! $data) {
                return new FX_Error("Failed to retrieve cached URL in RetrieveFM7Data()");
            }
            $data = $data["Body"];
        } elseif( $this->isFOpenQuery ) {
/*
Amendment by G G Thorsen -> ggt667@gmail.com, this function is written to read files exported using File Export in FMSA 10 and newer
This function is particularly written for huge queries of data that are less likely to change often and that would otherwise choke FM WPE
*/
            $f = fopen( $this->dataServer, 'rb' );
            $data = '';
            if( ! $f ) {
                return new FX_Error( "Failed to retrieve FOpen( '" . $this->dataServer . "', 'rb' ) File not found?" );
            } else {
                while( ! feof( $f ) )
                    $data .= fread( $f, 4096 );
                fclose( $f );
            }
        } elseif ($this->isPostQuery) {
            if ($this->useCURL && defined("CURLOPT_TIMEVALUE")) {
                $curlHandle = curl_init(str_replace($this->dataURLParams, '', $this->dataURL));
                curl_setopt($curlHandle, CURLOPT_POST, 1);
                curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $this->dataURLParams);
                ob_start();
                if (! curl_exec($curlHandle)) {
                    $this->lastDebugMessage .= "<p>Unable to connect to FileMaker.  Use the DEBUG constant and try connecting with the resulting URL manually.<br />\n";
                    $this->lastDebugMessage .= "You should also double check the user name and password used, the server address, and WPE configuration.</p>\n";
                    return new FX_Error("cURL could not retrieve Post data in RetrieveFM7Data(). A bad URL is the most likely reason.");
                }
                curl_close($curlHandle);
                $data = trim(ob_get_contents());
                ob_end_clean();
                if (substr($data, -1) != '>') {
                    $data = substr($data, 0, -1);
                }
            } else {
                $dataDelimiter = "\r\n";
                $socketData = "POST /fmi/xml/{$FMFile} HTTP/1.0{$dataDelimiter}";
                if ((defined("DEBUG") and DEBUG) or DEBUG_FUZZY) {
                    $currentDebugString = "<p>Using socket [$socketData] - FileMaker URL: <a href=\"{$this->dataURL}\">{$this->dataURL}</a></p>\n";
                    $this->lastDebugMessage .= $currentDebugString;
                    if (defined("DEBUG") and DEBUG) {
                        echo $currentDebugString;
                    }
                }
                if (strlen(trim($this->userPass)) > 1) {
                    $socketData .= "Authorization: Basic " . base64_encode($this->DBUser . ':' . $this->DBPassword) . $dataDelimiter;
                }
                $socketData .= "Host: {$this->dataServer}:{$this->dataPort}{$dataDelimiter}";
                $socketData .= "Pragma: no-cache{$dataDelimiter}";
                $socketData .= "Content-length: " . strlen($this->dataURLParams) . $dataDelimiter;
                $socketData .= "Content-type: application/x-www-form-urlencoded{$dataDelimiter}";
                $socketData .= $dataDelimiter . $this->dataURLParams;

                // Check if SSL is required
                if ($this->useSSLProtocol) {
                    $protocol = "ssl://";
                } else {
                    $protocol = "";
                }

                // debug to see what protocol is being used
                if ((defined("DEBUG") and DEBUG) or DEBUG_FUZZY) {
                    $currentDebugString = "<p>Domain and Protocol are {$protocol}{$this->dataServer}</p>\n";
                    $this->lastDebugMessage .= $currentDebugString;
                    if (defined("DEBUG") and DEBUG) {
                        echo $currentDebugString;
                    }
                }

                $fp = fsockopen ($protocol . $this->dataServer, $this->dataPort, $this->errorTracking, $this->fxError, 30);
                if (! $fp) {
                    $this->lastDebugMessage .= "<p>Unable to connect to FileMaker.  Use the DEBUG constant and try connecting with the resulting URL manually.<br />\n";
                    $this->lastDebugMessage .= "You should also double check the user name and password used, the server address, and WPE configuration.</p>\n";
                    return new FX_Error( "Could not fsockopen the URL in retrieveFM7Data" );
                }
                fputs ($fp, $socketData);
                while (!feof($fp)) {
                    $data .= fgets($fp, 128);
                }
                fclose($fp);
                $pos = strpos($data, chr(13) . chr(10) . chr(13) . chr(10)); // the separation code
                $data = substr($data, $pos + 4) . "\r\n";
            }
        } else {
            $fp = fopen($this->dataURL, "r");
            if (! $fp) {
                $this->lastDebugMessage .= "<p>Unable to connect to FileMaker.  Use the DEBUG constant and try connecting with the resulting URL manually.<br />\n";
                $this->lastDebugMessage .= "You should also double check the user name and password used, the server address, and WPE configuration.</p>\n";
                return new FX_Error("Could not fopen URL in RetrieveFM7Data.");
            }
            while (!feof($fp)) {
                $data .= fread($fp, 4096);
            }
            fclose($fp);
        }
        $data = str_replace($this->invalidXMLChars, '', $data);
        return $data;
    }

    function BuildSQLSorts ()
    {
        $currentOrderBy = '';

        if (count($this->sortParams) > 0) {
            $counter = 0;
            $currentOrderBy .= ' ORDER BY ';
            foreach ($this->sortParams as $key1 => $value1) {
                $field = '';
                $sortOrder = '';   // prevent to report error in IDE. (msyk, Feb 1, 2012)
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

    function BuildSQLQuery ($action)
    {
        $currentLOP = 'AND';
        $logicalOperators = array();
        $LOPCount = 0;
        $currentSearch = '';
        $currentQuery = '';
        $counter = 0;
        $whereClause = '';

        $name = '';
        $value = '';
        $op = '';   // prevent to report error in IDE. (msyk, Feb 1, 2012)

        switch ($action) {
            case '-find':
                foreach ($this->dataParams as $key1 => $value1) {
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
                            $currentSearch .= $this->primaryKeyField . " = '" . $value . "'";
                            ++$counter;
                            break;
                        case '-script':
                        case '-script.prefind':
                        case '-script.presort':
                            return new FX_Error("The '-script' parameter is not currently supported for SQL.");
                            break;
                        default:
                            if ($op == "") {
                                $op = $this->defaultOperator;
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
                if ($this->selectColsSet) {
                    $currentQuery = "SELECT {$this->selectColumns} FROM {$this->layout}{$whereClause}" . $this->BuildSQLSorts();
                } else {
                    $currentQuery = "SELECT * FROM {$this->layout}{$whereClause}" . $this->BuildSQLSorts();
                }
                break;
            case '-delete':
                foreach ($this->dataParams as $key1 => $value1) {
                    foreach ($value1 as $key2 => $value2) {
                        $$key2 = $value2;
                    }
                    if ($name == '-recid') {
                        $currentQuery = "DELETE FROM {$this->layout} WHERE {$this->primaryKeyField} = '{$value}'";
                    }
                }
                break;
            case '-edit':
                $whereClause = ' WHERE 1 = 0'; // if someone wants to update all records, they need to specify such
                $currentQuery = "UPDATE {$this->layout} SET ";
                foreach ($this->dataParams as $key1 => $value1) {
                    foreach ($value1 as $key2 => $value2) {
                        $$key2 = $value2;
                    }
                    if ($name == '-recid') {
                        $whereClause = " WHERE {$this->primaryKeyField} = '{$value}'";
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
                foreach ($this->dataParams as $key1 => $value1) {
                    $name = '';
                    $value = '';
                    foreach ($value1 as $key2 => $value2) {
                        $$key2 = $value2;
                    }
                    if ($name == '-recid') {
                        $currentQuery = "DELETE FROM {$this->layout} WHERE {$this->primaryKeyField} = '{$value}'";
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
                $currentQuery = "INSERT INTO {$this->layout} {$tempColList} VALUES {$tempValueList}";
                break;
        }
        $currentQuery .= ';';
        return $currentQuery;
    }

    function RetrieveMySQLData ($action)
    {
        if (strlen(trim($this->dataServer)) < 1) {
            return new FX_Error('No MySQL server specified.');
        }
        if (strlen(trim($this->dataPort)) > 0) {
            $tempServer = $this->dataServer . ':' . $this->dataPort;
        } else {
            $tempServer = $this->dataServer;
        }
        $mysql_res = @mysql_connect($tempServer, $this->DBUser, $this->DBPassword); // although username and password are optional for this function, FX.php expects them to be set
        if ($mysql_res == false) {
            return new FX_Error('Unable to connect to MySQL server.');
        }
        if ($action != '-dbopen') {
            if (! mysql_select_db($this->database, $mysql_res)) {
                return new FX_Error('Unable to connect to specified MySQL database.');
            }
        }
        if (substr_count($action, '-db') == 0 && substr_count($action, 'names') == 0 && strlen(trim($this->layout)) > 0) {
            $theResult = mysql_query('SHOW COLUMNS FROM ' . $this->layout);
            if (! $theResult) {
                return new FX_Error('Unable to access MySQL column data: ' . mysql_error());
            }
            $counter = 0;
            $keyPrecedence = 0;
            while ($tempRow = mysql_fetch_assoc($theResult)) {
                $this->fieldInfo[$counter]['name'] = $tempRow['Field'];
                $this->fieldInfo[$counter]['type'] = $tempRow['Type'];
                $this->fieldInfo[$counter]['emptyok'] = $tempRow['Null'];
                $this->fieldInfo[$counter]['maxrepeat'] = 1;
                $this->fieldInfo[$counter]['extra'] = $tempRow['Key'] . ' ' . $tempRow['Extra'];
                if ($this->fuzzyKeyLogic) {
                    if (strlen(trim($this->primaryKeyField)) < 1 || $keyPrecedence < 3) {
                        if (substr_count($this->fieldInfo[$counter]['extra'], 'UNI ') > 0 && $keyPrecedence < 3) {
                            $this->primaryKeyField = $this->fieldInfo[$counter]['name'];
                            $keyPrecedence = 3;
                        } elseif (substr_count($this->fieldInfo[$counter]['extra'], 'auto_increment') > 0 && $keyPrecedence < 2) {
                            $this->primaryKeyField = $this->fieldInfo[$counter]['name'];
                            $keyPrecedence = 2;
                        } elseif (substr_count($this->fieldInfo[$counter]['extra'], 'PRI ') > 0 && $keyPrecedence < 1) {
                            $this->primaryKeyField = $this->fieldInfo[$counter]['name'];
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
            case '-new':
                $this->dataQuery = $this->BuildSQLQuery($action);
                if (FX::isError($this->dataQuery)) {
                    return $this->dataQuery;
                }
            case '-sqlquery': // note that there is no preceding break, as we don't want to build a query
                $theResult = mysql_query($this->dataQuery);
                if ($theResult === false) {
                    return new FX_Error('Invalid query: ' . mysql_error());
                } elseif ($theResult !== true) {
                    if (substr_count($action, '-find') > 0 || substr_count($this->dataQuery, 'SELECT ') > 0) {
                        $this->foundCount = mysql_num_rows($theResult);
                    } else {
                        $this->foundCount = mysql_affected_rows($theResult);
                    }
                    if ($action == '-dup' || $action == '-edit') {
                        // pull in data on relevant record
                    }
                    $currentKey = '';
                    while ($tempRow = mysql_fetch_assoc($theResult)) {
                        foreach ($tempRow as $key => $value) {
                            if ($this->useInnerArray) {
                                $tempRow[$key] = array($value);
                            }
                            if ($key == $this->primaryKeyField) {
                                $currentKey = $value;
                            }
                        }
                        if ($this->genericKeys || $this->primaryKeyField == '') {
                            $this->currentData[] = $tempRow;
                        } else {
                            $this->currentData[$currentKey] = $tempRow;
                        }
                    }
                } else {
                    $this->currentData = array();
                }
                break;
            case '-findany':
                break;
            case '-dup':
                break;
        }
        $this->fxError = 0;
        return true;
    }

    function RetrievePostgreSQLData ($action)
    {
        $connectString = '';
        $unsupportedActions = array('-dbnames', '-layoutnames', '-scriptnames', '-dbopen', '-dbclose');

        if (in_array($action, $unsupportedActions)) {
            return new FX_Error("The requested Action ({$action}) is not supported in PostgreSQL via FX.php.");
        }
        if (strlen(trim($this->dataServer)) > 0) {
            $connectString .= " host={$this->dataServer}";
        }
        if (strlen(trim($this->dataPort)) > 0) {
            $connectString .= " port={$this->dataPort}";
        }
        if (strlen(trim($this->database)) > 0) {
            $connectString .= " dbname={$this->database}";
        }
        if (strlen(trim($this->DBUser)) > 0) {
            $connectString .= " user={$this->DBUser}";
        }
        if (strlen(trim($this->DBPassword)) > 0) {
            $connectString .= " password={$this->DBPassword}";
        }
        if (strlen(trim($this->urlScheme)) > 0 && $this->urlScheme == 'https') {
            $connectString .= " sslmode=require";
        }
        $postresql_res = @pg_connect($connectString);
        if ($postresql_res == false) {
            return new FX_Error("Unable to connect to PostgreSQL server. (" . pg_last_error($postresql_res) . ")");
        }
        $theResult = pg_query($postresql_res, "SELECT column_name, data_type, is_nullable FROM information_schema.columns WHERE table_name ='{$this->layout}'");
        if (! $theResult) {
            return new FX_Error('Unable to access PostgreSQL column data: ' . pg_last_error($postresql_res));
        }
        $counter = 0;
        $keyPrecedence = 0;
        while ($tempRow = @pg_fetch_array($theResult, $counter, PGSQL_ASSOC)) {
            $this->fieldInfo[$counter]['name'] = $tempRow['column_name'];
            $this->fieldInfo[$counter]['type'] = $tempRow['data_type'];
            $this->fieldInfo[$counter]['emptyok'] = $tempRow['is_nullable'];
            $this->fieldInfo[$counter]['maxrepeat'] = 1;
            ++$counter;
        }
        switch ($action) {
            case '-delete':
            case '-edit':
            case '-find':
            case '-findall':
            case '-new':
                $this->dataQuery = $this->BuildSQLQuery($action);
                if (FX::isError($this->dataQuery)) {
                    return $this->dataQuery;
                }
            case '-sqlquery': // note that there is no preceding break, as we don't want to build a query
                $theResult = pg_query($this->dataQuery);
                if (! $theResult) {
                    return new FX_Error('Invalid query: ' . pg_last_error($postresql_res));
                }
                if (substr_count($action, '-find') > 0 || substr_count($this->dataQuery, 'SELECT ') > 0) {
                    $this->foundCount = pg_num_rows($theResult);
                } else {
                    $this->foundCount = pg_affected_rows($theResult);
                }
                if ($action == '-dup' || $action == '-edit') {
                    // pull in data on relevant record
                }
                $counter = 0;
                $currentKey = '';
                while ($tempRow = @pg_fetch_array($theResult, $counter, PGSQL_ASSOC)) {
                    foreach ($tempRow as $key => $value) {
                        if ($this->useInnerArray) {
                            $tempRow[$key] = array($value);
                        }
                        if ($key == $this->primaryKeyField) {
                            $currentKey = $value;
                        }
                    }
                    if ($this->genericKeys || $this->primaryKeyField == '') {
                        $this->currentData[] = $tempRow;
                    } else {
                        $this->currentData[$currentKey] = $tempRow;
                    }
                    ++$counter;
                }
                break;
            case '-findany':
                break;
            case '-dup':
                break;
        }
        $this->fxError = 0;
        return true;
    }

    function RetrieveOpenBaseData ($action)
    {
        $availableActions = array('-delete', '-edit', '-find', '-findall', '-new', '-sqlquery');
        $columnTypes = array( 1 => 'char', 2 => 'integer', 3 => 'float', 4 => 'long', 5 => 'money', 6 => 'date', 7 => 'time', 8 => 'object', 9 => 'datetime', 10 => 'longlong', 11 => 'boolean', 12 => 'binary', 13 => 'text', 14 => 'timestamp');

        if (! in_array(strtolower($action), $availableActions)) { // first off, toss out any requests for actions NOT supported under OpenBase
            return new FX_Error("The action requested ({$action}) is not supported by OpenBase via FX.php.");
        }
        // although username and password are optional for this function, FX.php expects them to be set
        $openBase_res = ob_connect($this->database, $this->dataServer, $this->DBUser, $this->DBPassword);
        if (substr(trim($openBase_res), 0, 13) != 'Resource id #') {
            return new FX_Error("Error {$theResult}.  Unable to connect to OpenBase database.");
        }
        switch ($action) {
            case '-delete':
            case '-edit':
            case '-find':
            case '-findall':
            case '-new':
                $this->dataQuery = $this->BuildSQLQuery($action);
                if (FX::isError($this->dataQuery)) {
                    return $this->dataQuery;
                }
            case '-sqlquery': // note that there is no preceding break, as we don't want to build a query
                ob_makeCommand($openBase_res, $this->dataQuery);
                $theResult = ob_executeCommand($openBase_res);
                if (! $theResult) {
                    $tempErrorText = ob_servermessage($openBase_res);
                    ob_disconnect($openBase_res); // ob_disconnect() is not in the documentation
                    return new FX_Error("Unsuccessful query: $this->dataQuery ({$tempErrorText})");
                }
                $fieldCount = ob_resultColumnCount($openBase_res);
                for ($i = 0; $i < $fieldCount; ++$i) {
                    $this->fieldInfo[$i]['name'] = ob_resultColumnName($openBase_res, $i);
                    $this->fieldInfo[$i]['type'] = ob_resultColumnType($openBase_res, $i);
                    $this->fieldInfo[$i]['emptyok'] = 'NO DATA';
                    $this->fieldInfo[$i]['maxrepeat'] = 1;
                    $this->fieldInfo[$i]['extra'] = '';
                }
                $this->foundCount = ob_rowsAffected($openBase_res);
                $retrieveRow = array();
                $currentKey = '';
                while (ob_resultReturned($openBase_res) && ob_nextRowWithArray($openBase_res, $retrieveRow)) {
                    $tempRow = array();
                    foreach ($retrieveRow as $key => $value) {
                        if (! $this->useInnerArray) {
                            $tempRow[$this->fieldInfo[$key]['name']] = $value;
                        } else {
                            $tempRow[$this->fieldInfo[$key]['name']] = array($value);
                        }
                        if ($key == $this->primaryKeyField) {
                            $currentKey = $value;
                        } elseif ($this->primaryKeyField == '' && $this->fieldInfo[$key]['name'] == '_rowid') {
                            $currentKey = $value;
                        }
                    }
                    if (($this->genericKeys || $this->primaryKeyField == '') && strlen(trim($currentKey)) < 1) {
                        $this->currentData[] = $tempRow;
                    } else {
                        $this->currentData[$currentKey] = $tempRow;
                    }
                }
                break;
            default:
                return new FX_Error("The action requested ({$action}) is not supported by OpenBase via FX.php.");
                break;
        }
        $this->fxError = 0;
        return true;
    }

    function RetrieveODBCData ($action)
    {
        $availableActions = array('-delete', '-edit', '-find', '-findall', '-new', '-sqlquery');

        if (! in_array(strtolower($action), $availableActions)) { // first off, toss out any requests for actions NOT supported under ODBC
            return new FX_Error("The action requested ({$action}) is not supported under ODBC via FX.php.");
        }
        $odbc_res = odbc_connect($this->database, $this->DBUser, $this->DBPassword); // although username and password are optional for this function, FX.php expects them to be set
        if ($odbc_res == false) {
            return new FX_Error('Unable to connect to ODBC data source.');
        }
        switch ($action) {
            case '-delete':
            case '-edit':
            case '-find':
            case '-findall':
            case '-new':
                $this->dataQuery = $this->BuildSQLQuery($action);
                if (FX::isError($this->dataQuery)) {
                    return $this->dataQuery;
                }
            case '-sqlquery': // note that there is no preceding break, as we don't want to build a query
                $odbc_result = odbc_exec($odbc_res, $this->dataQuery);
                if (! $odbc_result) {
                    $tempErrorText = odbc_errormsg($odbc_res);
                    odbc_close($odbc_res);
                    return new FX_Error("Unsuccessful query: $this->dataQuery ({$tempErrorText})");
                }
                $this->foundCount = odbc_num_rows($odbc_result);
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
                    $this->fieldInfo[$fieldNumber]['name'] = odbc_result($odbc_columns, 'COLUMN_NAME');
                    $this->fieldInfo[$fieldNumber]['type'] = odbc_result($odbc_columns, 'TYPE_NAME');
                    $this->fieldInfo[$fieldNumber]['emptyok'] = odbc_result($odbc_columns, 'IS_NULLABLE');
                    $this->fieldInfo[$fieldNumber]['maxrepeat'] = 1;
                    $this->fieldInfo[$fieldNumber]['extra'] = 'COLUMN_SIZE:' . odbc_result($odbc_columns, 'COLUMN_SIZE') . '|BUFFER_LENGTH:' . odbc_result($odbc_columns, 'BUFFER_LENGTH') . '|NUM_PREC_RADIX:' . odbc_result($odbc_columns, 'NUM_PREC_RADIX');
                }
                while (odbc_fetch_row($odbc_result)) {
                    $tempRow = array();
                    for ($i = 1; $i <= $fieldCount; ++$i) {
                        $theResult = odbc_result($odbc_result, $i);
                        if (! $this->useInnerArray) {
                            $tempRow[$this->fieldInfo[$i]['name']] = $theResult;
                        } else {
                            $tempRow[$this->fieldInfo[$i]['name']] = array($theResult);
                        }
                        if ($this->fieldInfo[$i]['name'] == $this->primaryKeyField) {
                            $currentKey = $theResult;
                        }
                    }
                    if ($this->genericKeys || $this->primaryKeyField == '') {
                        $this->currentData[] = $tempRow;
                    } else {
                        $this->currentData[$currentKey] = $tempRow;
                    }
                }
                break;
            default:
                return new FX_Error("The action requested ({$action}) is not supported by FileMaker under ODBC via FX.php.");
                break;
        }
        $this->fxError = 0;
        return true;
    }

    function RetrieveCAFEphp4PCData ($action) // uncomment this section ONLY on Windows, or the COM object will cause the PHP parser to die
    {
        /*
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
            $this->lastDebugMessage .= $currentDebugString;
            if (defined("DEBUG") and DEBUG) {
                echo $currentDebugString;
            }
        }
        $theResult = $CAFEphp_res->Connect($this->database, $this->DBUser, $this->DBPassword);
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
                $this->dataQuery = $this->BuildSQLQuery($action);
                if (FX::isError($this->dataQuery)) {
                    return $this->dataQuery;
                }
            case '-sqlquery': // note that there is no preceding break, as we don't want to build a query
                if (substr(trim($this->dataQuery), 0, 6) == 'SELECT') {
                    $currentSelect = true;
                    $theResult = $CAFEphp_res->Query($this->dataQuery, $this->groupSize);
                } else {
                    $currentSelect = false;
                    $theResult = $CAFEphp_res->Execute($this->dataQuery);
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
                $this->foundCount = $theResult;
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
                    $this->fieldInfo[$i]['name'] = $theResult;
                    $this->fieldInfo[$i]['type'] = 'NO DATA';
                    $this->fieldInfo[$i]['emptyok'] = 'NO DATA';
                    $this->fieldInfo[$i]['maxrepeat'] = 'NO DATA';
                    $this->fieldInfo[$i]['extra'] = '';
                }
                if ($currentSelect) {
                    $tempRow = array();
                    for ($i = 0; $i < $this->foundCount; ++$i) {
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
                            if (! $this->useInnerArray) {
                                $tempRow[$this->fieldInfo[$j]['name']] = $theResult;
                            } else {
                                $tempRow[$this->fieldInfo[$j]['name']] = array($theResult);
                            }
                            if ($this->fieldInfo[$j]['name'] == $this->primaryKeyField) {
                                $currentKey = $value;
                            }
                        }
                        if ($this->genericKeys || $this->primaryKeyField == '') {
                            $this->currentData[] = $tempRow;
                        } else {
                            $this->currentData[$currentKey] = $tempRow;
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
        $this->fxError = 0;
        return true;
        */
    }

    function ExecuteQuery ($action)
    {
        switch (strtolower($this->dataServerType)) {
            case 'fmpro5':
            case 'fmpro6':
            case 'fmpro5/6':
                if ((defined("DEBUG") and DEBUG) or DEBUG_FUZZY) {
                    $currentDebugString = "<p>Accessing FileMaker Pro 5/6 data.</p>\n";
                    $this->lastDebugMessage .= $currentDebugString;
                    if (defined("DEBUG") and DEBUG) {
                        echo $currentDebugString;
                    }
                }
                $data = $this->RetrieveFMData($action);
                if (FX::isError($data)) {
                    return $data;
                }

                $xml_parser = xml_parser_create("UTF-8");
                xml_set_object($xml_parser, $this);
                xml_set_element_handler($xml_parser, "StartElement", "EndElement");
                xml_set_character_data_handler($xml_parser, "ElementContents");
                $xmlParseResult = xml_parse($xml_parser, $data, true);
                if (! $xmlParseResult) {
                    $theMessage = sprintf("ExecuteQuery XML error: %s at line %d",
                        xml_error_string(xml_get_error_code($xml_parser)),
                        xml_get_current_line_number($xml_parser));
                    xml_parser_free($xml_parser);
                    $this->lastDebugMessage .= "<p>Unable to parse FileMaker XML.  Use the DEBUG constant and try connecting with the resulting URL manually.<br />\n";
                    $this->lastDebugMessage .= "You should also double check the <strong>user name</strong> and <strong>password</strong> used, the <strong>server address and port</strong>, and <strong>Web Companion configuration</strong>.<br />\n";
                    $this->lastDebugMessage .= "Finally, be sure that you have specified the correct <strong>data type</strong> (e.g. FileMaker 5 or 6 versus 7 or 8.)</p>\n";
                    return new FX_Error($theMessage);
                }
                xml_parser_free($xml_parser);
                break;
            case 'fmpro7':
            case 'fmpro8':
            case 'fmpro9':
            case 'fmpro10':
            case 'fmpro11':
                if ((defined("DEBUG") and DEBUG) or DEBUG_FUZZY) {
                    $currentDebugString = "<p>Accessing FileMaker Pro 7/8/9 data.</p>\n";
                    $this->lastDebugMessage .= $currentDebugString;
                    if (defined("DEBUG") and DEBUG) {
                        echo $currentDebugString;
                    }
                }
                $data = $this->RetrieveFM7Data($action);
                if (FX::isError($data)) {
                    return $data;
                }

                $xml_parser = xml_parser_create("UTF-8");
                xml_set_object($xml_parser, $this);
                xml_set_element_handler($xml_parser, "StartElement", "EndElement");
                xml_set_character_data_handler($xml_parser, "ElementContents");
                $xmlParseResult = xml_parse($xml_parser, $data, true);
                if (! $xmlParseResult) {
/* Masayuki Nii added at Oct 9, 2009 */
					$this->columnCount = -1; 
					xml_parser_free($xml_parser);
                	$xml_parser = xml_parser_create("UTF-8");
                	xml_set_object($xml_parser, $this);
                	xml_set_element_handler($xml_parser, "StartElement", "EndElement");
                	xml_set_character_data_handler($xml_parser, "ElementContents");
                	$xmlParseResult = xml_parse($xml_parser, ConvertSurrogatePair( $data ), true);
                	if (! $xmlParseResult) {
/* ==============End of the addition */            	
                	$theMessage = sprintf("ExecuteQuery XML error: %s at line %d",
                        xml_error_string(xml_get_error_code($xml_parser)),
                        xml_get_current_line_number($xml_parser));
                    xml_parser_free($xml_parser);
                    $this->lastDebugMessage .= "<p>Unable to parse FileMaker XML.  Use the DEBUG constant and try connecting with the resulting URL manually.<br />\n";
                    $this->lastDebugMessage .= "You should also double check the <strong>user name</strong> and <strong>password</strong> used, the <strong>server address and port</strong>, and <strong>WPE configuration</strong>.<br />\n";
                    $this->lastDebugMessage .= "Finally, be sure that you have specified the correct <strong>data type</strong> (e.g. FileMaker 5 or 6 versus 7 or 8.)</p>\n";
                    return new FX_Error($theMessage);
/* Masayuki Nii added at Oct 9, 2009 */
                	}
/* ==============End of the addition */            	
                	}
                xml_parser_free($xml_parser);
                break;
            case 'openbase':
                if ((defined("DEBUG") and DEBUG) or DEBUG_FUZZY) {
                    $currentDebugString = "<p>Accessing OpenBase data.</p>\n";
                    $this->lastDebugMessage .= $currentDebugString;
                    if (defined("DEBUG") and DEBUG) {
                        echo $currentDebugString;
                    }
                }
                $openBaseResult = $this->RetrieveOpenBaseData($action);
                if (FX::isError($openBaseResult)) {
                    return $openBaseResult;
                }
                break;
            case 'mysql':
                if ((defined("DEBUG") and DEBUG) or DEBUG_FUZZY) {
                    $currentDebugString = "<p>Accessing MySQL data.</p>\n";
                    $this->lastDebugMessage .= $currentDebugString;
                    if (defined("DEBUG") and DEBUG) {
                        echo $currentDebugString;
                    }
                }
                $mySQLResult = $this->RetrieveMySQLData($action);
                if (FX::isError($mySQLResult)) {
                    return $mySQLResult;
                }
                break;
            case 'postgres':
                if ((defined("DEBUG") and DEBUG) or DEBUG_FUZZY) {
                    $currentDebugString = "<p>Accessing PostgreSQL data.</p>\n";
                    if ($this->fuzzyKeyLogic) {
                        $currentDebugString .= "<p>WARNING: Fuzzy key logic is not supported for PostgreSQL.</p>\n";
                    }
                    $this->lastDebugMessage .= $currentDebugString;
                    if (defined("DEBUG") and DEBUG) {
                        echo $currentDebugString;
                    }
                }
                $postgreSQLResult = $this->RetrievePostgreSQLData($action);
                if (FX::isError($postgreSQLResult)) {
                    return $postgreSQLResult;
                }
                break;
            case 'odbc':
                if ((defined("DEBUG") and DEBUG) or DEBUG_FUZZY) {
                    $currentDebugString = "<p>Accessing data via ODBC.</p>\n";
                    $this->lastDebugMessage .= $currentDebugString;
                    if (defined("DEBUG") and DEBUG) {
                        echo $currentDebugString;
                    }
                }
                $odbcResult = $this->RetrieveODBCData($action);
                if (FX::isError($odbcResult)) {
                    return $odbcResult;
                }
                break;
            case 'cafephp4pc':
                if ((defined("DEBUG") and DEBUG) or DEBUG_FUZZY) {
                    $currentDebugString = "<p>Accessing CAFEphp data.</p>\n";
                    $this->lastDebugMessage .= $currentDebugString;
                    if (defined("DEBUG") and DEBUG) {
                        echo $currentDebugString;
                    }
                }
                $CAFEphpResult = $this->RetrieveCAFEphp4PCData($action);
                if (FX::isError($CAFEphpResult)) {
                    return $CAFEphpResult;
                }
                break;
        }
    }

    function BuildLinkQueryString ()
    {
        $tempQueryString = '';
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST') {
            $paramSetCount = 0;
            $appendFlag = true;
            foreach ($_POST as $key => $value) {
                if ($appendFlag && strcasecmp($key, '-foundSetParams_begin') != 0 && strcasecmp($key, '-foundSetParams_end') != 0) {
                    $tempQueryString .= urlencode($key) . '=' . urlencode($value) . '&';
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

    function AssembleDataSet ($returnData)
    {
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
                $this->lastURL = $this->dataURL;
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
                if (defined('FX_OBJECTIVE'))	{
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

                $dataSet['foundCount'] = $this->foundCount;
                $dataSet['fields'] = $this->fieldInfo;
                $dataSet['URL'] = $this->dataURL;
                $dataSet['query'] = $this->dataQuery;
                $dataSet['errorCode'] = $this->fxError;
                $dataSet['valueLists'] = $this->valueLists;

                $this->lastFoundCount = $this->foundCount;
                $this->lastFields = $this->fieldInfo;
                $this->lastURL = $this->dataURL;
                $this->lastQuery = $this->dataQuery;
                $this->lastQueryParams = $this->dataParams;
                $this->lastErrorCode = $this->fxError;
                $this->lastValueLists = $this->valueLists;

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
        return $dataSet;
    }

    function FMAction ($Action, $returnDataSet, $returnData, $useInnerArray)
    {
        if ( $this->forceFlatten )  {           // Added by msyk, Feb 1, 2012
            $this->useInnerArray = false;
        } else {
            $this->useInnerArray = $useInnerArray;
        }                                       // ====================
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

    function SetDBData ($database, $layout="", $groupSize=50, $responseLayout="") // the layout parameter is equivalent to the table to be used in SQL queries
    {
        $this->database = $database;
        $this->layout = $layout;
        $this->groupSize = $groupSize;
        $this->responseLayout = $responseLayout;
        $this->ClearAllParams();
        $this->lastDebugMessage .= '<p>Configuring database connection...</p>';
    }

    function SetDBPassword ($DBPassword, $DBUser='FX') // Note that for historical reasons, password is the FIRST parameter for this function
    {
        if ($DBUser == '') {
            $DBUser = 'FX';
        }
        $this->DBPassword = $DBPassword;
        $this->DBUser = $DBUser;
        $this->lastDebugMessage .= '<p>Setting user name and password...</p>';
    }

    function SetDBUserPass ($DBUser, $DBPassword='') // Same as above function, but paramters are in the opposite order
    {
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

    function SetDefaultOperator ($op)
    {
        $this->defaultOperator = $op;
        return true;
    }

    function AddDBParam ($name, $value, $op="")                          // Add a search parameter.  An operator is usually not necessary.
    {
        if ($this->dataParamsEncoding  != '' && defined('MB_OVERLOAD_STRING')) {
            $this->dataParams[]["name"] = mb_convert_encoding($name, $this->dataParamsEncoding, $this->charSet);
            end($this->dataParams);
            $convedValue = mb_convert_encoding($value, $this->dataParamsEncoding, $this->charSet);
/* Masayuki Nii added at Oct 10, 2009 */
            if ( ! defined('SURROGATE_INPUT_PATCH_DISABLED') && $this->charSet == 'UTF-8')	{
				$count = 0;
				for ($i=0; $i< strlen($value); $i++)	{
					$c = ord(substr( $value, $i, 1 ));
					if ( ( $c == 0xF0 )&&( (ord(substr( $value, $i+1, 1 )) & 0xF0) == 0xA0 ))	{
						$i += 4;	$count++;
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

    function AddDBParamArray ($paramsArray, $paramOperatorsArray=array())   // Add an array of search parameters.  An operator is usually not necessary.
    {
        foreach ($paramsArray as $key => $value) {
            if (isset($paramOperatorsArray[$key]) && strlen(trim($paramOperatorsArray[$key])) > 0) {
                $this->AddDBParam($key, $value, $paramOperatorsArray[$key]);
            } else {
                $this->AddDBParam($key, $value);
            }
        }
    }

    function SetPortalRow ($fieldsArray, $portalRowID=0, $relationshipName='')
    {
        foreach ($fieldsArray as $fieldName => $fieldValue) {
            if (strlen(trim($relationshipName)) > 0 && substr_count($fieldName, '::') < 1) {
                $this->AddDBParam("{$relationshipName}::{$fieldName}.{$portalRowID}", $fieldValue);
            } else {
                $this->AddDBParam("{$fieldName}.{$portalRowID}", $fieldValue);
            }
        }
    }

    function SetRecordID ($recordID)
    {
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

    function SetModID ($modID)
    {
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

    function SetLogicalOR ()
    {
        $this->AddDBParam('-lop', 'or');
    }

    // FileMaker 7 only
    function SetFMGlobal ($globalFieldName, $globalFieldValue)
    {
        $this->AddDBParam("{$globalFieldName}.global", $globalFieldValue);
    }

    function PerformFMScript ($scriptName)                              // This function is only meaningful when working with FileMaker data sources
    {
        $this->AddDBParam('-script', $scriptName);
    }

    function PerformFMScriptPrefind ($scriptName)                       // This function is only meaningful when working with FileMaker data sources
    {
        $this->AddDBParam('-script.prefind', $scriptName);
    }

    function PerformFMScriptPresort ($scriptName)                       // This function is only meaningful when working with FileMaker data sources
    {
        $this->AddDBParam('-script.presort', $scriptName);
    }

    function AddSortParam ($field, $sortOrder="", $performOrder=0)        // Add a sort parameter.  An operator is usually not necessary.
    {
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

    function FMSkipRecords ($skipSize)
    {
        $this->currentSkip = $skipSize;
    }

    function FMPostQuery ($isPostQuery = true)
    {
        $this->isPostQuery = $isPostQuery;
    }

    function FMFOpenQuery ($isFOpenQuery = true)
    {
        $this->isFOpenQuery = $isFOpenQuery;
    }

    function FMUseCURL ($useCURL = true)
    {
        $this->useCURL = $useCURL;
    }

    // By default, FX.php adds an extra layer to the returned array to allow for repeating fields and portals.
    // When these are not present, or when accessing SQL data, this may not be desirable.  FlattenInnerArray() removes this extra layer.
    function FlattenInnerArray ()
    {
        $this->forceFlatten = true;
    }

/* The actions that you can send to FileMaker start here */

    function FMDBOpen ()
    {
        $queryResult = $this->ExecuteQuery("-dbopen");
        if (FX::isError($queryResult)){
            return $queryResult;
        }
    }

    function FMDBClose ()
    {
        $queryResult = $this->ExecuteQuery("-dbclose");
        if (FX::isError($queryResult)){
            return $queryResult;
        }
    }

    function FMDelete ($returnDataSet = false, $returnData = 'basic', $useInnerArray = true)
    {
        return $this->FMAction("-delete", $returnDataSet, $returnData, $useInnerArray);
    }

    function FMDup ($returnDataSet = true, $returnData = 'full', $useInnerArray = true)
    {
        return $this->FMAction("-dup", $returnDataSet, $returnData, $useInnerArray);
    }

    function FMEdit ($returnDataSet = true, $returnData = 'full', $useInnerArray = true)
    {
        return $this->FMAction("-edit", $returnDataSet, $returnData, $useInnerArray);
    }

    function FMFind ($returnDataSet = true, $returnData = 'full', $useInnerArray = true)
    {
        return $this->FMAction("-find", $returnDataSet, $returnData, $useInnerArray);
    }

    function FMFindAll ($returnDataSet = true, $returnData = 'full', $useInnerArray = true)
    {
        return $this->FMAction("-findall", $returnDataSet, $returnData, $useInnerArray);
    }

    function FMFindAny ($returnDataSet = true, $returnData = 'full', $useInnerArray = true)
    {
        return $this->FMAction("-findany", $returnDataSet, $returnData, $useInnerArray);
    }

    function FMNew ($returnDataSet = true, $returnData = 'full', $useInnerArray = true)
    {
        return $this->FMAction("-new", $returnDataSet, $returnData, $useInnerArray);
    }

    function FMView ($returnDataSet = true, $returnData = 'full', $useInnerArray = true)
    {
        return $this->FMAction("-view", $returnDataSet, $returnData, $useInnerArray);
    }

    function FMDBNames ($returnDataSet = true, $returnData = 'full', $useInnerArray = true)
    {
        return $this->FMAction("-dbnames", $returnDataSet, $returnData, $useInnerArray);
    }

    function FMLayoutNames ($returnDataSet = true, $returnData = 'full', $useInnerArray = true)
    {
        return $this->FMAction("-layoutnames", $returnDataSet, $returnData, $useInnerArray);
    }

    function FMScriptNames ($returnDataSet = true, $returnData = 'full', $useInnerArray = true)
    {
        return $this->FMAction("-scriptnames", $returnDataSet, $returnData, $useInnerArray);
    }

    // DoFXAction() is a general purpose action function designed to streamline FX.php code
    function DoFXAction ($currentAction, $returnDataSet = true, $useInnerArray = false, $returnType = 'object')
    {
        return $this->FMAction($currentAction, $returnDataSet, $returnType, $useInnerArray);
    }

/* The actions that you can send to FileMaker end here */
    // PerformSQLQuery() is akin to the FileMaker actions above with two differences:
    //  1) It is SQL specific
    //  2) The SQL query passed is the sole determinant of the query performed (AddDBParam, etc. will be ignored)
    function PerformSQLQuery ($SQLQuery, $returnDataSet = true, $useInnerArray = false, $returnData = 'object')
    {
        $this->dataQuery = $SQLQuery;
        return $this->FMAction("-sqlquery", $returnDataSet, $returnData, $useInnerArray);
    }

    // SetDataKey() is used for SQL queries as a way to provide parity with the RecordID/ModID combo provided by FileMaker Pro
    function SetDataKey ($keyField, $modifyField = '', $separator = '.')
    {
        $this->primaryKeyField = $keyField;
        $this->modifyDateField = $modifyField;
        $this->dataKeySeparator = $separator;
        return true;
    }

    // SetSelectColumns() allows users to specify which columns should be returned by an SQL SELECT statement
    function SetSelectColumns ($columnList)
    {
        $this->selectColsSet = true;
        $this->selectColumns = $columnList;
        return true;
    }

    // SQLFuzzyKeyLogicOn() can be used to have FX.php make it's best guess as to a viable key in an SQL DB
    function SQLFuzzyKeyLogicOn ($logicSwitch = false)
    {
        $this->fuzzyKeyLogic = $logicSwitch;
        return true;
    }

    // By default, FX.php uses records' keys as the indices for the returned array.  UseGenericKeys() is used to change this behavior.
    function UseGenericKeys ($genericKeys=true)
    {
        $this->genericKeys = $genericKeys;
        return true;
    }
    
    // Added by Masayuki Nii(nii@msyk.net) Dec 18, 2010
    // Modifye by msyk, Feb 1, 2012
    function RemainAsArray (
    			$rArray1,$rArray2=NULL,$rArray3=NULL,$rArray4=NULL,$rArray5=NULL,
    			$rArray6=NULL,$rArray7=NULL,$rArray8=NULL,$rArray9=NULL,$rArray10=NULL,
    			$rArray11=NULL,$rArray12=NULL)	{
    	$this->portalAsRecord = false;
    	$counter = 0;
    	for ( $i=1 ; $i<13 ; $i++ )	{
            $valName = "rArray{$i}";
     		if ( ! isset($$valName) )	{
    			break;
    		}
    		if (is_array($$valName))	{
    			$this->portalAsRecord = true;
    			$isFirstTime = true;
                $firstItemName = '';
    			foreach($$valName as $item)	{
    				if($isFirstTime)	{
    					$isFirstTime = false;
    					$firstItemName = $item;
    					$this->remainNamesReverse[$item] = true;
    				} else {
    					$this->remainNamesReverse[$item] = $firstItemName;
    				}
    				$this->remainName[$counter] = $item;
    				$counter++;
    			}	
    		} else {
    			$this->remainName[$counter] = $$valName;
    			$this->remainNamesReverse[$$valName] = true;
    			$counter++;
    		}
    	}
    }
    
    // Added by Masayuki Nii(nii@msyk.net) Dec 18, 2010
    function isRemaiName($fieldName)	{
    	foreach($this->remainName as $fName)	{
     		if (strpos($fieldName,$fName) === 0)	{
    			return true;
    		}
    	}
    	return false;
    }
    
    // Added by Masayuki Nii(nii@msyk.net) Dec 18, 2010
    function getTOCName($fieldName)	{
    	$p = strpos($fieldName,'::');
    	if ( $p === false )	{
    		return 'ERROR-TOC name is conflicted.';
    	}
    	$tocName = substr($fieldName,0,$p);
    	if ($this->remainNamesReverse[$tocName] !== true)	{
    		return $this->remainNamesReverse[$tocName];
    	}
    	return $tocName;
    }
}

/* Convert wrong surrogated-pair character to light code sequence in UTF-8
 * Masayuki Nii (msyk@msyk.net) Oct 9, 2009
 * Refered http://www.nii.ac.jp/CAT-ILL/about/system/vista.html
 */
function ConvertSurrogatePair($data)	{
	$altData = '';
	for ($i=0; $i<strlen($data); $i++)	{
		$c = substr( $data, $i, 1 );
		if (( ord($c) == 0xed )&&( (ord(substr( $data, $i+1, 1 )) & 0xF0) == 0xA0 ))	{
			for ( $j = 0; $j < 6 ; $j++ )
				$utfSeq[] = ord(substr($data, $i+$j,1));
			$convSeq[3] = $utfSeq[5];
			$convSeq[2] = $utfSeq[4] & 0x0F | (($utfSeq[2] & 0x03) << 4) | 0x80;
			$topDigit = ($utfSeq[1] & 0x0F) + 1;
			$convSeq[1] = (($utfSeq[2] >> 2) & 0x0F) | (($topDigit & 0x03) << 4) | 0x80;
			$convSeq[0] = (($topDigit >> 2) & 0x07) | 0xF0;
			$c = chr( $convSeq[0] ).chr( $convSeq[1] ).chr( $convSeq[2] ).chr( $convSeq[3] );
			$i += 5;
		}
		$altData .= $c;
	}
	return $altData;
}
?>