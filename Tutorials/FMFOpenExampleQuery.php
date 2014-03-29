/*
This function is written to read files exported using FileMaker XML Export in FMSA 7 and newer

This function is particularly written for huge queries of data, that are less likely
to change often and that would otherwise choke FM WPE

It will also benefit frequent queries, and where data is updated through publishing to file,
such as news articles, product descriptions, and similar use.

Suggested use, export as XML without XSLT to your own xml folder of webserver as an example:

/var/www/com.example.www/xml/product/<<productnumber>>.fmpxmlresult.xml
/var/www/com.example.www/xml/news/<<newsnumber>>.fmpxmlresult.xml
/var/www/com.example.www/xml/article/<<articlenumber>>.fmpxmlresult.xml
/var/www/com.example.www/xml/order/<<ordernumber>>.fmpxmlresult.xml
*/

<?php

$q = new FX( '/var/www/com.example.www/xml/order/1234.xml' );
$q->FMFOpenQuery( true );
$r = $q->FMFind();

print_r( $r );

?>

/*
The only thing that should be left for direct communication via WPE in your solution when using this
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

<?php

$q = new FX( $host, $sandeman );
$q->SetDBData( 'WorldWideWait', 'xmlOrderStatusFlag' );
$q->AddDBParam( '-recid', $recid );
$q->AddDBParam( 'orderStatus', 5 );
$q->SetDBPassword( $xmlPass, $xmlUser );
$r = $q->FMEdit();

print_r( $r );

?>
