<?php

/*
FX->FMFOpenQuery( true );

This function is written to read files exported using FileMaker XML Export in FMSA 7 and newer.

This function is particularly written for querying data of the write once, read many, 
that are less likely to change often and that would otherwise choke FM WPE,
the full product catalogue? That you can export to your web space every time you make updates in FileMaker?
Product descriptions? News articles?

Suggested use, export as XML without XSLT to your own xml folder of your webspace as an example:

/var/www/com.example.www/xml/product/<<productnumber>>.fmpxmlresult.xml
/var/www/com.example.www/xml/news/<<newsnumber>>.fmpxmlresult.xml
/var/www/com.example.www/xml/article/<<articlenumber>>.fmpxmlresult.xml
/var/www/com.example.www/xml/order/<<ordernumber>>.fmpxmlresult.xml
 */

//$q = new FX( 'file:///var/www/com.example.www/xml/order/' . $o . '.fmpxmlresult.xml' );
$q = new FX( '/var/www/com.example.www/xml/order/' . $o . '.fmpxmlresult.xml' );
//$q = new FX( 'http://www.example.com/xml/order/' . $o . '.fmpxmlresult.xml' );
$q->FMFOpenQuery( true );
$r = $q->FMFind();

print_r( $r );

/*
The only thing that should be left for direct communication via WPE in your solution when using this approach
should be live order data, and places where you will have to set flags in the order process.

These cases can be optimized by making layouts for individual queries;
as you already have the recid in /var/www/com.example.www/xml/order/<<ordernumber>>.xml

You will only need something like a layout for example by the name of: xmlOrderStatusFlag
with only one number field orderStatus

and to update this order you will only need the order number from $_SESSION[$myaccount][$currentorder]
of some sort to find the -recid in /var/www/com.example.www/xml/order/<<ordernumber>>.xml

And to set the orderStatus from WorldPay or the likes, saying paid in full is 5,
you will have to do an FMEdit of -recid found above, to set the orderStatus

 */

$q = new FX( $dinnerForOne, $sandeman );
$q->SetDBData( 'WorldWideWait', 'xmlOrderStatusFlag' );
$q->AddDBParam( '-recid', $recid );
$q->AddDBParam( 'orderStatus', 5 );
$q->SetDBPassword( $xmlPass, $xmlUser );
$r = $q->FMEdit();

print_r( $r );

/*
Part of the struggle with FileMaker's XML RPC is that it only serves on 1 NIC,
with this apporach we avoid using those NICs, we actually publish this on the disk,
and let nginx or apache handle fopen calls from its 0.0.0.0

most normal servers serve to the local routing table 0.0.0.0, while FMSA serves to 1 of the NICs on your FMSA,
this is a bit of a paranoid approach and and is a very efficient way of creating a bottle neck.

Imagine an FMSA node with several NICs

1 NIC for LAN FMAPP users
1 NIC for WAN FMAPP users
1 NIC for WPE used with mailrobots
1 NIC for WPE used with webservices
1 NIC for WPE used with mailserver
1 NIC for WPE used with openvpn
1 NIC for dev

Well, not determined by the NICs, rather by the switches and infrastructure.

Another part of this struggle is how many fields are put on the layout used in the XML RPC query,
the math of bandwidth distribution is simple it follows the principles of the multilplication table.

The more fields and the bigger then content, the slower the query.

For the purpose of bandwidth distribution it is not desirable to make the total space more narrow than it already is.


A typical real world example below

 */

$tmpStaticFile = 'http://www.example.com/xml/order/' . $o . '.fmpxmlresult.xml';
if( uriexists( $tmpStaticFile ) ) {
  $q = new FX( $tmpStaticFile );
  $q->FMFOpenQuery( true );
} else {
  $q = new FX( $dinnerForOne, $sandeman );
  $q->SetDBData( 'WorldWideWait', 'xmlOrderStatusFlag' );
  $q->AddDBParam( 'ordernumber', $_POST['ordernumber'], 'eq' );
  $q->SetDBPassword( $xmlPass, $xmlUser );
}
$r = $q->FMFind();

/*

uriexists implementation below:

 */

function uriexists( $uri ) {
// $o = output
// $e = error code
// $ch = cURL handler
  $ch = curl_init( $uri );
  curl_setopt( $ch, CURLOPT_NOBODY, true );
  curl_exec( $ch );
  $e = curl_getinfo( $ch, CURLINFO_HTTP_CODE );

  if( $e == 200 ) {
    $o = true;
  } else {
    $o = false;
  }
  curl_close( $ch );
  return $o;
}

?>
