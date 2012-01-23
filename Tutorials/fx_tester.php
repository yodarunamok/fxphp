<?php

/*********************************************************************
 * Feel free to e-mail any comments or questions to FX@iviking.org.  *
 * Please remember that this code is being released as open source   *
 * under The Artistic License of PERL fame...                        *
 * http://www.opensource.org/licenses/artistic-license.html          *
 *...and is also covered by the FX.php license addendum...           *
 * http://www.iviking.org/downloads/ADDENDUM.txt                     *
 *********************************************************************/

require_once('../server_data.php');
require_once('../FX.php');
require_once('../Developer/FMErrors.php');

if (DEBUG_FUZZY) {
    $messageType = 'Fuzzy';
} else {
    $messageType = 'FX.php';
}

if (isset($_POST['find_records'])) { // a search is only preformed if the form was submitted

    // by placing the form values in an array, we can set all values for our search with a single function call
    $searchRecordsArray = array('First_Namer' => $_POST['fname'], 'Last_Name' => $_POST['lname'], 'Phone_1 ' => $_POST['phone']);
    // configure a connection to FileMaker Server Advanced
    $contactsListQuery = new FX($serverIP, $webCompanionPort, $dataSourceType);
    // set database and layout information
    $contactsListQuery->SetDBData('Contacts.fp7', 'web_list');
    // set database username and password
    $contactsListQuery->SetDBUserPass($webUN, $webPW);
    // add parameter array for new record
    $contactsListQuery->AddDBParamArray($searchRecordsArray);
    // create a new record
    $contactsList = $contactsListQuery->DoFXAction('perform_find');

} else { // otherwise, find all records

    // configure a connection to FileMaker Server Advanced
    $contactsListQuery = new FX($serverIP, $webCompanionPort, $dataSourceType);
    // set database and layout information
    $contactsListQuery->SetDBData('Contacts.fp7', 'web_list', 'all');
    // set database username and password
    $contactsListQuery->SetDBUserPass($webUN, $webPW);
    // retrieve all records in this database available to the current user
    $contactsList = $contactsListQuery->DoFXAction('show_all');

}

?>
<html>
    <head>
        <title>FX Error Tester</title>
    </head>
    <body>
        <h1>Contact List</h1>
        <table border="1">
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
                <td colspan="3" style="background-color:#999999">&nbsp;</td>
            </tr>
            <form action="fx_tester.php" method="post" name="find_records">
                <tr>
                    <td><input type="text" name="fname" value="" size="10" /></td>
                    <td><input type="text" name="lname" value="" size="10" /></td>
                    <td><input type="text" name="phone" value="" size="12" /></td>
                </tr>
                <tr>
                    <td colspan="3" align="center">
                        <input type="submit" name="find_records" value="Search" />
                        <input type="submit" name="show_all" value="Show All" />
                        <input type="reset" name="clear" value="Clear" />
                    </td>
                </tr>
            </form>
        </table>
        <br /><hr /><br />
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