<?php

/*********************************************************************
 * Feel free to e-mail any comments or questions to FX@iviking.org.  *
 * Please remember that this code is being released as open source   *
 * under The Artistic License of PERL fame...                        *
 * http://www.opensource.org/licenses/artistic-license.html          *
 *********************************************************************/

require_once('../lib/server_data.php');
require_once('../FX.php');
require_once('../lib/FMErrors.php');

if (DEBUG_FUZZY) {
    $messageType = 'Fuzzy';
} else {
    $messageType = 'FX.php';
}

$pageSize = 10;
if (isset($_GET['skip'])) $skip = $_GET['skip'];
else $skip = 0;

// these first configuration options are the same whether we're finding all records, or just a subset...

// configure a connection to FileMaker Server Advanced
$contactsListQuery = new FX($serverIP, $webCompanionPort, $dataSourceType);
// set database and layout information
$contactsListQuery->SetDBData('Contacts', 'web_list', $pageSize);
// set database username and password
$contactsListQuery->SetDBUserPass($webUN, $webPW);
// specify the records to skip before returning any ($skip is set above)
$contactsListQuery->SetSkipSize($skip);

// the last bit of the query follows; note that it is different, depending on the action desired

// are we performing a find?
if (isset($_GET['find_records'])) {

    // by placing the form values in an array, we can set all values for our search with a single function call
    $searchRecordsArray = array('First_Name' => $_GET['fname'], 'Last_Name' => $_GET['lname'], 'Phone_1 ' => $_GET['phone']);
    // add parameter array for find
    $contactsListQuery->AddDBParamArray($searchRecordsArray);
    // perform the find
    $contactsList = $contactsListQuery->DoFXAction('perform_find');

} else { // otherwise, find all records

    // retrieve all records in this database available to the current user
    $contactsList = $contactsListQuery->DoFXAction('show_all');

}

// these variables are informational
$setStart = $skip + 1;
$setEnd = $skip + count($contactsList);

// set up previous and next links (which may not be links at all, depending...)
if (strlen($contactsListQuery->lastLinkPrevious) > 0) {
    $backLink = "<a href='{$contactsListQuery->lastLinkPrevious}'>Previous</a>";
} else {
    $backLink = "<span class='inactiveLink'>Previous</span>";
}
if (strlen($contactsListQuery->lastLinkNext) > 0 || ($skip + $pageSize) < $contactsListQuery->lastFoundCount) {
    $nextLink = "<a class='nextLink' href='{$contactsListQuery->lastLinkNext}'>Next</a>";
} else {
    $nextLink = "<span class='nextLink inactiveLink'>Next</span>";
}

?>
<html>
    <head>
        <title>FX Error Tester</title>
        <style type="text/css">
            html {
                font-size: .75em;
                font-family: Helvetica, Arial, sans-serif;
            }
            h1, h4 {
                text-align: center;
            }
            h1, h4, table {
                margin: .5em auto;
                min-width: 25em;
            }
            hr {
                max-width: 20em;
            }
            td {
                padding: .25em;
            }
            pre {
                max-width: 25em;
                margin: .5em auto;
                font-family: monospace;
            }
            .nextLink {
                float: right;
            }
            .inactiveLink {
                color: #999999;
            }
            .dividerRow {
                background-color: #999999;
            }
        </style>
    </head>
    <body>
        <h1>Contact List</h1>
        <h4>(Records <?=$setStart?> to <?=$setEnd?> of <?=$contactsListQuery->lastFoundCount?>)</h4>
        <table border="1">
            <tr>
                <td colspan="3"><?=$backLink?> <?=$nextLink?></td>
            </tr>
            <tr>
                <th>First Name</th>
                <th>Last Name</th>
                <th>Phone Number</th>
            </tr>
<?php

if (FX::isError($contactsList)) {
    echo "            <tr>\n";
    echo "                <td colspan=\"3\" align=\"center\">ERROR</td>\n";
    echo "            </tr>\n";
} elseif (count($contactsList) < 1) {
    echo "            <tr>\n";
    echo "                <td colspan=\"3\" align=\"center\">No Records Found</td>\n";
    echo "            </tr>\n";
} else {
    foreach ($contactsList as $contact) {
        echo "            <tr>\n";
        echo "                <td>{$contact['First_Name']}</td>\n";
        echo "                <td>{$contact['Last_Name']}</td>\n";
        echo "                <td>{$contact['Phone_1']}</td>\n";
        echo "            </tr>\n";
    }
}

?>
            <tr>
                <td colspan="3"><?=$backLink?> <?=$nextLink?></td>
            </tr>
            <tr>
                <td colspan="3" class="dividerRow">&nbsp;</td>
            </tr>
            <form action="index.php" method="get" name="find_records">
                <tr>
                    <td><input type="text" name="fname" value="" size="20"></td>
                    <td><input type="text" name="lname" value="" size="20"></td>
                    <td><input type="text" name="phone" value="" size="20"></td>
                </tr>
                <tr>
                    <td colspan="3" align="center">
                        <input type="submit" name="find_records" value="Search">
                        <input type="submit" name="show_all" value="Show All">
                        <input type="reset" name="clear" value="Clear">
                    </td>
                </tr>
            </form>
        </table>
        <br><hr><br>
        <pre>
<?php

echo("Error Code: {$contactsListQuery->lastErrorCode}\n\n");
echo("FileMaker Error Message: {$errorsList[$contactsListQuery->lastErrorCode]}\n\n");
echo("{$messageType} Error Message: \n<blockquote>{$contactsListQuery->lastDebugMessage}\n</blockquote>\n\n");

/*
print_r($contactsList);
echo("\n\n");
print_r($contactsListQuery);
*/

?>
        </pre>
    </body>
</html>