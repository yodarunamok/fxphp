<?php

/*********************************************************************
 * Please remember that this code is being released as open source   *
 * under The Artistic License of PERL fame...                        *
 * http://www.opensource.org/licenses/artistic-license.html          *
 *...and is also covered by the FX.php license addendum...           *
 * http://www.iviking.org/downloads/ADDENDUM.txt                     *
 *********************************************************************/

require_once('../server_data.php');
require_once('FileMaker.php');
require_once('../FX_Fuzzy_Debugger.php');

if (DEBUG_FUZZY) {
    $messageType = 'Fuzzy';
} else {
    $messageType = 'F.A.P.';
}

if (isset($_POST['find_records'])) { // a search is only preformed if the form was submitted

    // by placing the form values in an array, we can loop to set all of our find criteria
    $searchRecordsArray = array('First_Namer' => $_POST['fname'], 'Last_Name' => $_POST['lname'], 'Phone_1 ' => $_POST['phone']);
    // configure a connection to FileMaker Server Advanced
    $contactsListConnection = new FileMaker('Contacts.fp7', $serverIP . ':' . $webCompanionPort, $webUN, $webPW);
    // set database and layout information
    $contactsListQuery = $contactsListConnection->newFindCommand('web_list');
    // add find parameters
    foreach ($searchRecordsArray as $fieldName => $fieldValue) {
        $contactsListQuery->addFindCriterion($fieldName, $fieldValue);
    }
    // retrieve the records in this database matching the specified parameters available to the current user
    $contactsObject = $contactsListQuery->execute();

} else { // otherwise, find all records

    // configure a connection to FileMaker Server Advanced
    $contactsListConnection = new FileMaker('Contacts.fp7', $serverIP . ':' . $webCompanionPort, $webUN, $webPW);
    // create a new findall query
    $contactsListQuery = $contactsListConnection->newFindAllCommand('web_list');
    // perform query
    $contactsObject = $contactsListQuery->execute();

}
$fuzzyData = new FX_Fuzzy_Debugger($contactsListConnection, $contactsObject);

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

if (FileMaker::isError($contactsObject)) {
    $errorCode = $contactsObject->code;
    $errorMessage = $contactsObject->getMessage();
    echo "            <tr>\n";
    echo "                <td colspan=\"3\" align=\"center\">ERROR</td>\n";
    echo "            </tr>\n";
} elseif ($contactsObject->getFoundSetCount() < 1) {
    echo "            <tr>\n";
    echo "                <td colspan=\"3\" align=\"center\">No Records Found</td>\n";
    echo "            </tr>\n";
} else {
    $errorCode = 0;
    $errorMessage = 'No Error';
    // pull out just records
    $contactsList = $contactsObject->getRecords();
    foreach ($contactsList as $contact) {
        echo "            <tr>\n";
        echo "                <td>" . $contact->getField('First_Name') . "</td>\n";
        echo "                <td>" . $contact->getField('Last_Name') . "</td>\n";
        echo "                <td>" . $contact->getField('Phone_1') . "</td>\n";
        echo "            </tr>\n";
    }
}

?>
            <tr>
                <td colspan="3" style="background-color:#999999">&nbsp;</td>
            </tr>
            <form action="fx_tester_fap.php" method="post" name="find_records">
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

echo("Error Code: {$errorCode}\n\n");
echo("FileMaker Error Message: {$errorMessage}\n\n");
echo("{$messageType} Error Message: \n<blockquote>{$fuzzyData->fuzzyOut}\n</blockquote>\n\n");

/*
print_r($contactsList);
echo("\n\n");
print_r($contactsListQuery);
*/

?>
        </pre>
    </body>
</html>