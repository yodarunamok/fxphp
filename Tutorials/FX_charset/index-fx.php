<?php							// '<?' or '<?php' tells PHP to start parsing
/*********************************************************************
 * The comments herein are designed to be helpful to someone with	*
 * little or no programming experience.  To that end, many of the	*
 * comments may address things will appear obvious to many coders.   *
 * For the most part I'll place my comments at the end of each line. *
 * Feel free to e-mail any comments or questions to FX@iviking.org.  *
 * Please remember that this code is being released as open source   *
 * under The Artistic License of PERL fame:						  *
 * http://www.opensource.org/licenses/artistic-license.html		  *
 *********************************************************************/
/* Modified by Masayuki Nii (2004/3/23), for Japanese language supporting. */
/* Modified by Masayuki Nii (2004/8/13), for adding Japanese field name support. */
/* Modified by Masayuki Nii (2004/9/4), for UTF-8. */

header('Content-Type: text/html;charset=utf-8');	// Added by msyk 2004/3/23
// For avoiding MOJI-BAKE( invalid character ), please saving this file by UTF-8 chacter set.
// このファイルはUTF-8で保存して利用してください。

include_once( 'FX/FX.php' );
	// FX_charset.php contains the class for pulling data
	// from FileMaker into PHP -- 'include_once()'
	// makes sure the class is only declared once.
include_once( 'server_data.php' );
	// To make sure that these examples work for you, be sure
	// to set the IP address of your server in server_data.php
	// IMPORTANT: The leading '$' denotes a variable in PHP

$BookQuery = new FX($serverIP, $webCompanionPort, 'FMPro7');	
	// This line creates an instance of the FX class
	// If you use version 6 pro/unlimited, modify to 'FMPro5/6' from 'FMPro7'.

$BookQuery->SetDBData("Book_List.fp7", "Detail_View");
	// The '->' indicates that SetDBData is part of the FX instance we just created.

$arrayName = 'HTTP_' . $HTTP_SERVER_VARS["REQUEST_METHOD"] . '_VARS';
	// Note the '$$' a couple of lines down.  I'm using a variable
	// whose name is the contents of another variable.  VERY handy.

// **** Added by msyk 2004/3/23 **** To make a new record in DB.
if (${$arrayName}['currentQuery'] == 'New Record') {
	foreach( $HTTP_POST_VARS as $key => $value )	{
		if (($key != 'currentQuery') && ($key != 'currentSort'))	{
			$BookQuery->AddDBParam($key, stripslashes( $value ));
		}
	}
	$BookQuery->FMNew();
}

//$BookQuery->SetDBData("Book_List.fp7", "Book_List");
	// The extension in the file name omited for multi version compatibility(msyk)

if (${$arrayName}['currentSort'] != '') {	// If sorting has been requested, this adds it to the query.
	$BookQuery->AddSortParam($HTTP_GET_VARS['currentSort']);
}
if (${$arrayName}['currentQuery'] == 'Search Book List!') {	// Check if this page is being accessed by a search
	foreach ($$arrayName as $key => $value) {	// 'foreach()' is a VERY handy function.  It steps
										// through an array and stores the data in temporary
										// variables as directed ($key and $value in this case)
		if ($key != 'currentSort' && $key != 'currentQuery') {
			$BookQuery->AddDBParam($key, $value);	// '$key' contains the name of the field to search in,
		}										// '$value' contains the value we hope to find.
		$currentSearch .= '&' . "$key=" . urlencode($value);	// The '.' and '.=' operators concatenate expressions
	}

	$BookData = $BookQuery->FMFind();	// This performs a find based on the specified parameters.
}
else {
	$currentSearch = '';
	$BookData = $BookQuery->FMFindAll();		// Shows all records in the database
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
	<head>
		<title>iViking FX -- Book List Demo Page</title>
		<style type="text/css">
			<!--
				.whitetxt	{color: #FFFFFF}
			-->
		</style>
	</head>

	<body bgcolor="#FFFFFF">
		<h2 align="left">iViking FX -- Book List Demo Page</h2>
		<table cellspacing="0" cellpadding="0" border="0">
			<tr bgcolor="#333333">
				<th align="left" width="150">
					<a href="?currentSort=author<?php echo $currentSearch; ?>" class="whitetxt">Author</a>
				</th>
				<th align="left" width="300">
					<a href="?currentSort=title<?php echo $currentSearch; ?>" class="whitetxt">Title [ 日本語名]</a>
				</th>
				<th align="left" width="25">
					<a href="?currentSort=number_of_pages<?php echo $currentSearch; ?>" class="whitetxt">Pages</a>
				</th>
			</tr>
<?php
$counter = 1;
if ($BookData['foundCount'] > 0) {					// Determine if any books were found.
	foreach ($BookData['data'] as $key => $value) {	// The 'data' subarray contains the search results from FileMaker
		$recordID = strtok($key, '.');				// The main 'key' in the data subarry is constructed like this:
											// FileMaker Record ID, a '.', and FileMaker's Modification ID
											// (The latter of these is a value that FileMaker increments each
											//  time a record is modified.)
		if ($counter % 2 == 0) {					// '%' is the modulus operator in PHP.
			echo "<tr bgcolor=\"#CCCCCC\">\n";	// 'echo' is one method of displaying content as it is
		}									// parsed.  The '=' used in the lines above is shorthand
		else {								// for 'echo' when used in the manner shown.
			echo "<tr>\n";						// A backslash in PHP usually indicates that the parser
		}									// should handle the following character in a special way --
											// '\n' is a newline character, '\"' inserts a double quote.
		echo "<td align=\"left\" valign=\"top\">";
		echo $value['author'][0];					// When we step through the 'data' subarray, each '$value' is a
		echo "</td>\n";	// FileMaker record.  Here we're displaying the first value in
						// the 'author' field for the current record.  Repeating fields
						// and portals (where present) may contain multiple values.
		echo "<td align=\"left\" valign=\"top\">";
		echo "<a href=\"detail-fx.php?ID=$recordID&query=" . urlencode($currentSearch) . "\">";
		echo $value['title'][0];
		echo "</a> [ ";
		echo $value['日本語名'][0];		// Setting the key as Japanese character.
		echo " ] </td>\n";
		echo "<td align=\"right\" valign=\"top\">";
		echo $value['number_of_pages'][0];
		echo "&nbsp;&nbsp;</td>\n";
		echo "</tr>\n";
		++$counter;		// The '++' operator increments the argument it accompanies.
	}
}
else {		// Here's the message to display if no records are found.
}
?>
			<tr bgcolor="#333333">
				<td colspan="3">&nbsp;</td>
			</tr>
			<tr>
				<td colspan="3">&nbsp;</td>
			</tr>
			<tr>
				<td colspan="1" align="left">
					<form action="index-utf8.php" method="post">
						<table cellspacing="1" cellpadding="2" border="2" align="left">
							<tr>
								<td colspan="2" align="left"><big>Search&nbsp;for&nbsp;a&nbsp;Book</big></td>
							</tr>
							<tr>
								<td align="left">Author:&nbsp;</td>
								<td align="left"><input type="text" size="20" name="author"></td>
							</tr>
							<tr>
								<td align="left">Title:&nbsp;</td>
								<td align="left"><input type="text" size="20" name="title"></td>
							</tr>
							<tr>
								<td align="center" colspan="2"><input type="submit" name="currentQuery" value="Search Book List!"></td>
							</tr>
							<tr>
								<td align="center" colspan="2"><input type="submit" name="currentQuery" value="Show All..."></td>
							</tr>
						</table>
					</form>
				</td>
				<!--		// Added by msyk 2004/3/23	-->
				<td>
					 <form action="index-utf8.php" method="post">
						<table cellspacing="1" cellpadding="2" border="2" align="right">
							<tr>
								<td colspan="2" align="left"><big>Input Book</big></td>
							</tr>
						<tr><td>Author:</td><td><input type="text" size="20" name="author"></td></tr>
						<tr><td>Title:</td><td><input type="text" size="20" name="title"></td></tr>
						<tr><td>日本語名:</td><td><input type="text" size="20" name="日本語名"></td></tr>
						<tr><td>ISBN:</td><td><input type="text" size="20" name="ISBN"></td></tr>
						<tr><td>Description:</td><td><textarea name="description" cols="20" rows="5"></textarea></td></tr>
						<tr><td colspan="2" align="center">
						<input type="submit" name="currentQuery" value="New Record">
						<input type="hidden" name="currentSort" value="">
						</table>
					 </form>
				</td>
			</tr>
		</table>
	</body>
</html>