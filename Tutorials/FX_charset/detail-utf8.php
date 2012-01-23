<?php							// '<?' or '<?php' tells PHP to start parsing
/********************************************************************
 * The comments herein are designed to be helpful to someone with	*
 * little or no programming experience.  To that end, many of the	*
 * comments may address things will appear obvious to many coders.   *
 * For the most part I'll place my comments at the end of each line. *
 * Feel free to e-mail any comments or questions to FX@iviking.org.  *
 * Please remember that this code is being released as open source   *
 * under The Artistic License of PERL fame:						  *
 * http://www.opensource.org/licenses/artistic-license.html		  *
 *********************************************************************/
/* Modified by Masayuki Nii (2004/8/13), for adding Japanese field name support. */

header('Content-Type: text/html;charset=utf-8');	// Added by msyk 2004/8/13
// For avoiding MOJI-BAKE( invalid character ), please saving this file by UTF-8 chacter set.
// このファイルはUTF-8で保存して利用してください。

include_once( 'FX_charset.php' );
	// FX_charset.php contains the class for pulling data
	// from FileMaker into PHP -- 'include_once()'
	// makes sure the class is only declared once.
include_once( 'server_data.php' );
	// To make sure that these examples work for you, be sure
	// to set the IP address of your server in server_data.php
	// IMPORTANT: The leading '$' denotes a variable in PHP
$BookQuery = new FX_charset($serverIP, $webCompanionPort, 'FMPro7');		
	// This line creates an instance of the FX class
	// If you use version 6 pro/unlimited, modify to 'FMPro5/6' from 'FMPro7'.

//$BookQuery->SetCharacterEncoding('utf8');	// Added by msyk 2004/8/13
//$BookQuery->SetDataParamsEncoding('SJIS');	// Added by msyk 2004/8/13
//If you want to use UTF-8 only, you don't need to abve methods for FX_charset class.

$BookQuery->SetDBData("Book_List", "Detail_View");
	// The '->' indicates that SetDBData is part of the FX instance we just created.
$BookQuery->AddDBParam('-recid', $HTTP_GET_VARS['ID']);
	// '-recid' is a reference to the unique ID that FileMaker
	// creates for each record.  You'll also note that PHP
	// recognizes the parameters passed from the last page ('ID').
$query = $HTTP_GET_VARS['query'];
$BookData = $BookQuery->FMFind();
$currentKey = key($BookData['data']);	// From the online PHP manual:
	// key() returns the index element of the current array position
	// This is ideal in our case since the outer array has one element.

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
	<head>
		<title>iViking FX -- Book Detail Demo Page</title>
		<style type="text/css">
			<!--
				.whitetxt	{color: #FFFFFF}
			-->
		</style>
	</head>

	<body bgcolor="#FFFFFF">
		<h2 align="left">iViking FX -- Book List Detail Page</h2>
		<table cellspacing="0" cellpadding="2" border="0" bgcolor="#CCCCCC">
			<tr>
				<td colspan="6">&nbsp;</td>
			</tr>
			<tr>
				<td width="10" rowspan="5">&nbsp;</td>
				<td align="left" width="75" valign="top"><b>Author:&nbsp;</b></td>
				<td align="left" width="240" bgcolor="#FFFFFF">
					<?php echo $BookData['data'][$currentKey]['author'][0]; ?>
				</td>
				<td width="5" rowspan="5">&nbsp;</td>
				<td align="center" valign="center" bgcolor="#FFFFFF" width="125" rowspan="4">
					<img src="<?php echo "http://$serverIP:$webCompanionPort" . $BookData['data'][$currentKey]['cover_art'][0]; ?>"
							width="200">
				</td>
				<td width="10" rowspan="5">&nbsp;</td>
			</tr>
			<tr>
				<td align="left" valign="top"><b>Title:&nbsp;</b></td>
				<td align="left" bgcolor="#FFFFFF">
					<?php echo $BookData['data'][$currentKey]['title'][0]; ?>
					<br>日本名：<?php echo $BookData['data'][$currentKey]['日本語名'][0]; ?>
				</td>
			</tr>
			<tr>
				<td align="left" valign="top"><b>ISBN:&nbsp;</b></td>
				<td align="left" bgcolor="#FFFFFF">
					<?php echo $BookData['data'][$currentKey]['ISBN'][0]; ?>
				</td>
			</tr>
			<tr>
				<td align="left" valign="top"><b>Description:&nbsp;</b></td>
				<td align="left" valign="top" rowspan="2" bgcolor="#FFFFFF">
					<?php echo str_replace("\n", '<br />', $BookData['data'][$currentKey]['description'][0]); ?>
				</td>
			</tr>
			<tr>
				<td>&nbsp;</td>
				<td align="center" valign="center">
					<b>Pages:</b>&nbsp;<?php echo $BookData['data'][$currentKey]['number_of_pages'][0]; ?>
				</td>
			</tr>
			<tr>
				<td align="center" colspan="6">
					<a href="index-utf8.php?<?php echo $query; ?>">Return to Book List</a>
				</td>
			</tr>
		</table>
<hr>
Each character in the Title field:<br>
<?php
	mb_internal_encoding('utf8');
	$bookTitle = $BookData['data'][$currentKey]['title'][0];
	for ( $i = 0 ; $i < mb_strlen($bookTitle) ; $i++ )	{
		echo '　<font color="#BBBBBB">[' . $i . ']=></font>' . mb_substr($bookTitle, $i, 1 );
	}
?>
	</body>
</html>