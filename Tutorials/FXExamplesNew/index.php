<?php
define("DEBUG", false);
error_reporting(E_ALL);

// MICROSOFT IIS TWEAKS

if (! isset($_SERVER['DOCUMENT_ROOT'])) {
    global $DOCUMENT_ROOT;
    global $HTTP_SERVER_VARS;

    $_SERVER['DOCUMENT_ROOT'] = str_replace(str_replace('\\\\', '/', $_SERVER['PHP_SELF']), '', str_replace('\\\\', '/', $_SERVER['PATH_TRANSLATED'])) . '/';
    $DOCUMENT_ROOT = $_SERVER['DOCUMENT_ROOT'];
    $HTTP_SERVER_VARS['DOCUMENT_ROOT'] = $_SERVER['DOCUMENT_ROOT'];
}

// END MICROSOFT IIS TWEAKS

require_once($_SERVER['DOCUMENT_ROOT'] . "/FX/FX.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/FX/Developer/FMErrors.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/FX/server_data.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/FX/image_proxy.php");

$currentParameterArray = '_' . strtoupper($_SERVER['REQUEST_METHOD']);
foreach ($$currentParameterArray as $key => $value) {
    switch ($key) {
        case 'FXE_FMServerIP':
        case 'FXE_FMWCPort':
        case 'FXE_currentDatabaseName':
        case 'FXE_currentLayoutName':
        case 'FXE_groupSize':
        case 'skip':
        case 'FXE_action':
        case 'FXE_username':
        case 'FXE_password':
            $$key = $value;
            break;
        case '-recid':
            $FXE_currentRecord = $value;
        default:
            $newKey = str_replace('___PERIOD___', '.', $key);
            $formParams[$newKey] = $value;
            break;
    }
}
if (! isset($skip) || $skip == '') {
    $skip = 0;
}

$currentDatabaseList = array();                                                 // I'm initializing these arrays here, just in case they wouldn't contain valid data otherwise.
$currentLayoutList = array();
$viewData = array();

if (! isset($FXE_FMServerIP) || strlen(trim($FXE_FMServerIP)) == 0) $FXE_FMServerIP = $serverIP;
if (! isset($FXE_FMWCPort) || strlen(trim($FXE_FMWCPort)) == 0) $FXE_FMWCPort = $webCompanionPort;
if (! isset($FXE_currentDatabaseName) || strlen(trim($FXE_currentDatabaseName)) == 0) {
    $FXE_currentDatabaseName = 'Book_List.fp5';
    $FXE_currentLayoutName = 'Detail_View';
}
if (! isset($FXE_groupSize) || strlen(trim($FXE_groupSize)) == 0) $FXE_groupSize = 2;
if (! isset($FXE_action) || strlen(trim($FXE_action)) == 0) $FXE_action = 'display';
if (! isset($FXE_username) || strlen(trim($FXE_username)) == 0) $FXE_username = '';
if (! isset($FXE_password) || strlen(trim($FXE_password)) == 0) $FXE_password = '';

if (fopen("http://$FXE_FMServerIP:$FXE_FMWCPort/", 'r')) {
    $errorMessage = 'No valid Database specified.';
    $FXQuery = new FX($FXE_FMServerIP, $FXE_FMWCPort, $dataSourceType);         // This line creates an instance of the FX class.
    $DatabaseData = $FXQuery->FMDBNames();                                      // The '->' indicates that SetDBData is part of
                                                                                // the FX instance we just created.
    if (FX::isError($DatabaseData)) {
        echo("<pre>\n");
        print_r($LayoutData);
        echo("</pre>\n");
        exit;
    }
    foreach ($DatabaseData['data'] as $key => $value) {
        $currentDatabaseList[] = $value['DATABASE_NAME'][0];
    }
    unset($DatabaseData);
    if (in_array($FXE_currentDatabaseName, $currentDatabaseList)) {
        $FXQuery->SetDBData($FXE_currentDatabaseName);
        $FXQuery->SetDBPassword($FXE_password, $FXE_username);                  // Note that password is the FIRST parameter (since user names are only relevant for the FM Web Security DB.)
        $LayoutData = $FXQuery->FMLayoutNames();
        if (FX::isError($LayoutData)) {
            echo("<pre>\n");
            print_r($LayoutData);
            echo("</pre>\n");
            exit;
        }
        foreach ($LayoutData['data'] as $key => $value) {
            $currentLayoutList[] = $value['LAYOUT_NAME'][0];
        }
        unset($LayoutData);
        if (($FXE_currentLayoutName == '' && strtolower($dataSourceType) == 'fmpro5/6') || in_array($FXE_currentLayoutName, $currentLayoutList)) {
            $errorMessage = 'None';
            if (strtolower($FXE_action) == 'update') {
                $FXQuery->SetDBData($FXE_currentDatabaseName, $FXE_currentLayoutName);
                session_name('FXE_sessionID');
                session_start();
                if (! isset($HTTP_SESSION_VARS['currentLayout']) || (isset($HTTP_SESSION_VARS['currentLayout']) && $HTTP_SESSION_VARS['currentLayout'] != $FXE_currentLayoutName)) {
                    $editFields = array();
                    $HTTP_SESSION_VARS['currentLayout'] = $FXE_currentLayoutName;
                    foreach ($formParams as $key => $value) {
                        if ($key != '-recid' && strcasecmp($key, '-foundSetParams_begin') != 0 && strcasecmp($key, '-foundSetParams_end') != 0) {
                            if (is_array($value)) {
                                $value = implode("\n", $value);
                            }
                            $FXQuery->AddDBParam('-recid', $FXE_currentRecord);
                            $FXQuery->AddDBParam($key, $value);
                            $updateResult = $FXQuery->FMEdit(true, 'basic');
                        }
                        if ($key == '-recid' || (isset($updateResult['errorCode']) && $updateResult['errorCode'] == 0)) {
                            $editFields[] = $key;
                        }
                    }
                    $HTTP_SESSION_VARS['editFields'] = serialize($editFields);
                } else {
                    $editFields = unserialize($HTTP_SESSION_VARS['editFields']);
                    foreach ($editFields as $key => $value) {
                        if (is_array($formParams[$value])) {
                            $formParams[$value] = implode("\n", $formParams[$value]);
                        }
                        $FXQuery->AddDBParam($value, $formParams[$value]);
                    }
                    $updateResult = $FXQuery->FMEdit();
                    // echo($updateResult['URL']);
                }
            }
            $FXQuery->SetDBData($FXE_currentDatabaseName, $FXE_currentLayoutName, $FXE_groupSize);
            $FXQuery->FMSkipREcords($skip);
            $ReturnedData = $FXQuery->FMFindAll();                                      // This demo finds all records in the current database.
            if ($FXE_currentLayoutName != '') {
                $fieldLayout = array();
                $FXQuery->SetDBData($FXE_currentDatabaseName, $FXE_currentLayoutName);
                $viewData = $FXQuery->FMView();                                         // If a layout was specified, get the 'View' information for that layout.
                foreach ($viewData['fields'] as $key => $value) {
                    if (! isset($value['valuelist'])) {
                        $value['valuelist'] = '';
                    }
                    $fieldLayout[$value['name']] = array('type' => $value['type'], 'valuelist' => $value['valuelist']);
                }
            }
        } else {
            $errorMessage = 'No valid Layout specified.';
        }
    }
} else {
    $errorMessage = 'Specified port and/or IP are invalid.';
}
if ($errorMessage != 'None') {
    $FXE_currentLayoutName = '';
    if (substr_count($errorMessage, 'Database') > 0 || substr_count($errorMessage, 'port') > 0) {
        $FXE_currentDatabaseName = '';
    }
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
  <head>
    <title>iViking FX -- Dataset Example Page</title>
    <style type="text/css">
      <!--
      .body {
        font-family: Arial, Helvetica, sans-serif;
        font-size: 10px;
        font-style: normal;
        font-weight: normal;
        color: #000000
      }
      .smallsans {font: 9px/10px Helvetica, Arial, sans-serif}
      .bbody {
        font-weight: bold;
        font-size: 10px;
        font-family: Verdana, Arial, Helvetica, sans-serif
      }
      .wbbody {
        font-weight: bold;
        font-size: 10px;
        font-family: Verdana, Arial, Helvetica, sans-serif;
        color: #FFFFFF
      }
      .lgsans {font: 14px/15px Verdana, Arial, Helvetica, sans-serif}
      .blgsans {font: bold 14px/15px Verdana, Arial, Helvetica, sans-serif}
      .redtext {color: #FF0000}
      .bluetext {color: #0000FF}
      .dkgraytext {color: #333333}
      .medgraytext {color: #666666}
      .whitetext {color: #FFFFFF}
      -->
    </style>
    <script language="JavaScript">
      <!--
      function SubmitDBInfo() {
        self.document.DBInfo.FXE_currentLayoutName.selectedIndex = 0;
        self.document.DBInfo.submit();
      }
      // -->
    </script>
  </head>

  <body bgcolor="#FFFFFF">
    <table cellspacing="0" cellpadding="2" border="0" width="100%">
      <tr>
        <td align="center">
          <h2>FX Example Page</h2>
          <h4>These are the examples used in the FX Data Format Document</h4>
        </td>
      </tr>
      <tr>
        <td align="center">
          <table cellspacing="0" cellpadding="0" border="2">
            <tr>
              <!-- The following form contains the code for setting the current database information. -->
              <form method="post" action="index.php" name="DBInfo">
                <td align="center" valign="middle">
                  <table cellspacing="1" cellpadding="4" border="1" width="100%">
                    <tr bgcolor="#006633">
                      <td class="wbbody" align="center" valign="bottom">Database&nbsp;Name</td>
                      <td class="wbbody" align="center" valign="bottom">Layouts&nbsp;in<br><?php echo $FXE_currentDatabaseName; ?></td>
                      <td class="wbbody" align="center" valign="bottom">Records<br>per&nbsp;Page</td>
                      <td class="wbbody" align="center" valign="bottom">FileMaker&nbsp;IP<br>Address</td>
                      <td class="wbbody" align="center" valign="bottom">Web&nbsp;Companion<br>Port&nbsp;Number</td>
                    </tr>
                    <tr bgcolor="#006633">
                      <td align="center">
                        <select name="FXE_currentDatabaseName" onchange="if (this.value != '') SubmitDBInfo();">
                            <option value="">[ None ]</option>
<?php
foreach ($currentDatabaseList as $key => $value) {
    echo '                          <option value="'. $value . '"';
    if ($value == $FXE_currentDatabaseName) {
        echo ' selected';
    }
    echo ">$value</option>\n";
}
?>
                        </select>
                      </td>
                      <td align="center">
                        <select name="FXE_currentLayoutName">
                          <option value="">[ None ]</option>
<?php
foreach ($currentLayoutList as $key => $value) {
    echo '                          <option value="'. $value . '"';
    if ($value == $FXE_currentLayoutName) {
        echo ' selected';
    }
    echo ">$value</option>\n";
}
?>
                        </select>
                      </td>
                      <td align="center"><input type="text" name="FXE_groupSize" value="<?php echo $FXE_groupSize; ?>" size="4"></td>
                      <td align="center"><input type="text" name="FXE_FMServerIP" value="<?php echo $FXE_FMServerIP; ?>" size="20"></td>
                      <td align="center"><input type="text" name="FXE_FMWCPort" value="<?php echo $FXE_FMWCPort; ?>" size="6"></td>
                    </tr>
                    <tr bgcolor="#006633">
                        <td colspan="5" align="center">
                            <table cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td align="right" class="wbbody" valign="middle">User&nbsp;Name:&nbsp;</td>
                                    <td align="left"><input type="text" name="FXE_username" value="<?php echo $FXE_username; ?>" size="16"></td>
                                    <td align="center">&nbsp;</td>
                                    <td align="right" class="wbbody" valign="middle">Password:&nbsp;</td>
                                    <td align="left"><input type="password" name="FXE_password" value="<?php echo $FXE_password; ?>" size="16"></td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr bgcolor="#006633">
                      <td class="body" align="center" valign="middle" colspan="5">
                        <input type="submit" name="databaseSettings" value="Get Data!" class="body">
                        <input type="reset" value="Reset/Revert" class="body">
                      </td>
                    </tr>
                  </table>
                </td>
              </form>
            </tr>
            <tr>
              <td align="center" valign="middle">
                <!-- The following table contains the code for displaying field information for the current database. -->
                <table cellspacing="1" cellpadding="4" border="1" width="100%">
			<form action="parse.php" method="post">
                  <tr bgcolor="#666666">
                    <td class="wbbody" align="center" valign="bottom" colspan="8">Field&nbsp;Information</td>
                  </tr>
                  <tr bgcolor="#666666">
                    <td class="wbbody" align="center" valign="bottom">Field&nbsp;Name</td>
                    <td class="wbbody" align="center" valign="bottom">TYPE</td>
                    <td class="wbbody" align="center" valign="bottom">EMPTYOK</td>
                    <td class="wbbody" align="center" valign="bottom">MAXREPEAT</td>
                    <td class="wbbody" align="center" valign="bottom">CLASS</td>
                    <td class="wbbody" align="center" valign="bottom">STATUS</td>
                    <td class="wbbody" align="center" valign="bottom">MATCH</td>
                    <td class="wbbody" align="center" valign="bottom">VALUE LIST</td>
                  </tr>
<?php
$fieldStatus = array(
	'Disabled',
	'Plain output',
	'Image',
	'Image text as reference',
	'TEXT',
	'TEXTAREA',
	'Radio',
	'Checkbox',
	'Select'
);

$noYes = array(
	'No',
	'Yes'
);

if ($errorMessage == 'None') {
    $counter = 0;
    foreach ($ReturnedData['fields'] as $key => $value) {
	foreach( $fieldStatus as $v ) {
		$tmpFieldStatus[] = '<option value="'.$v.'">'.$v;
	}
	$vlFieldStatus = '<select name="vl['.$value['name'].']">' . implode( "\n", $tmpFieldStatus ) . '</select>';
	unset( $tmpFieldStatus );

	foreach( $noYes as $nYv ) {
		$tmpNoYes[] = '<option value="'.$nYv.'">'.$nYv;
	}
	$vlNoYes = '<select name="vlNoYes['.$value['name'].']">' . implode( "\n", $tmpNoYes ) . '</select>';
	unset( $tmpNoYes );

	if( count( $viewData['valueLists'] ) > 0) {
		foreach( $viewData['valueLists'] as $kk => $vv ) {
			$tmpValueLists[] = '<option value="'.$kk.'">'.$kk;
		}
		$vlValueLists = '<select name="vv['.$value['name'].']">' . implode( "\n", $tmpValueLists ) . '</select>';
		unset( $tmpValueLists );
	} else {
		$vlValueLists = 'N/A';
	}

        if ($counter % 2 == 0) {
            $rowColor = '#99CC99';
        } else {
            $rowColor = '#99CCCC';
        }
        echo "                  <tr bgcolor=\"$rowColor\">\n";
        echo '                    <td align="left" class="body">' . $value['name'] . "</td>\n";
        echo '                    <td align="center" class="body">' . $value['type'] . "</td>\n";
        echo '                    <td align="center" class="body">' . $value['emptyok'] . "</td>\n";
        echo '                    <td align="center" class="body">' . $value['maxrepeat'] . "</td>\n";
        echo '                    <td align="center" class="body"><input name="cl[' . $value['name'] . ']">' . "</td>\n";
	echo '                    <td align="center" class="body">' . $vlFieldStatus . "</td>\n";
	echo '                    <td align="center" class="body">' . $vlNoYes . "</td>\n";
	echo '                    <td align="center" class="body">' . $vlValueLists . "</td>\n";
        echo "                  </tr>\n";
        ++$counter;
    }
?>
                  <tr bgcolor="#666666">
                    <td class="wbbody" align="center" valign="bottom" colspan="8"><input name="a" type="submit" value="Make HTML Form"></td>
                  </tr>
			</form>
                      </table>
                    </td>
                  </tr>
                  <tr>
                    <td align="center" valign="middle">
                      <!-- The following table contains the code for displaying data and related information for the current database. -->
                      <table cellspacing="0" cellpadding="0" border="0" width="100%">
                        <tr>
                          <td>
                            <table cellspacing="1" cellpadding="4" border="1" width="100%">
                              <tr bgcolor="#CC9966">
                                <td align="right" class="bbody">URL:</td>
                                <td align="left" class="body" colspan="2"><?php echo $ReturnedData['URL']; ?></td>
                              </tr>
                              <tr bgcolor="#FF9999">
                                <td align="right" class="bbody">Error:</td>
                                <td align="center" class="body"><?php echo $ReturnedData['errorCode']; ?></td>
                                <td align="left" class="body"><?php echo $errorsList[$ReturnedData['errorCode']]; ?></td>
                              </tr>
                            </table>
                          </td>
                        </tr>
                      </table>
                    </td>
                  </tr>
<?php
} else {
    echo "                          <table cellspacing=\"1\" cellpadding=\"4\" border=\"1\" width=\"100%\">\n";
    echo "                            <tr bgcolor=\"#99CC99\">\n";
    echo '                              <td align="center" class="bbody" colspan="4">' . "$errorMessage</td>\n";
    echo "                            </tr>\n";
    echo "                          </table>\n";
    echo "                        </tr>\n";
}
?>
                      </table>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
    <pre>
        <?php // phpinfo(); ?>
        <?php print_r($_POST); ?>
    </pre>
  </body>
</html>
