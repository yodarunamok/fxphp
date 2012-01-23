<?php
/*******************************************************************************************\
|                                                                                           |
|   This file is part of the FX.php release from www.iviking.org and is released under the  |
|   artistic license and the FX license addendum (which are also included with the release. |
|                                                                                           |
|   The main purpose of these functions is to handle the secure output of image URLs from   |
|   FileMaker and the proxying of requests for these images.                                |
|                                                                                           |
|   For best security using these functions, before accessing images for the first time you |
|   should access the included echo_new_key.php file once; and paste the resulting key      |
|   within the quotes as the value of $encryt key in the "key section" below.  The key      |
|   inside the quotes should only be comprised of numbers and exclamation points (!).  Any  |
|   Other characters within the quotes should be removed.                                   |
|                                                                                           |
|   As a final note, please remember that it is possible to generate much higher security   |
|   measures within your individual solutions.  The use of sessions would be one way to do  |
|   this, but your individual needs may suggest other options.                              |
|                                                                                           |
|   -- Chris Hansen, creator of FX.php and VisualFX.php                                     |
|      email: FX@iviking.org                                                                |
|        web: www.iviking.org                                                               |
|                                                                                           |
\*******************************************************************************************/

// For best security with these functions, change this value as described above...
// Begin key section
$encryptKey = "187!90!196!54!194!210!98!53!174!113!107!147!39!177!80!220!248!230!201!46!55!211!108!166!136!168!205!136!184!38!128!56!220!90!63!141!155!42!85!54!228!161!159!227!131!167!142!105!104!107!219!120!57!139!246!154!231!188!202!98!124!122!147!117!62!231!201!198!65!154!247!145!181!74!61!85!129!68!182!41!175!100!166!141!40!247!115!189!208!254!241!93!182!198!210!95!226!44!233!105!62!86!194!144!174!71!86!122!63!75!231!60!216!63!184!89!132!110!48!229!78!183!253!171!212!137!246!156!144!157!238!94!250!100!113!225!140!151!84!241!190!165!253!162!229!108!175!119!197!56!172!132!139!194!237!112!215!158!254!79!253!134!216!164!191!197!224!169!78!93!55!222!70!117!248!127!215!235!139!231!234!84!51!117!174!68!194!135!191!42!235!85!116!193!166!98!106!156!89!195!208!118!217!67!142!152!215!173!174!226!76!206!244!194!121!184!180!243!124!240!140!228!189!173!171!115!88!114!211!198!145!85!91!224!133!213!186!50!155!83!106!79!204!44!227!168!163!115!159!62!245!208!61!57!115!124";
// End of key section

require_once('server_data.php');

$imageType = 'jpeg';
$forbiddenRequests = array('Ğdbnames', 'Ğdelete', 'Ğdup', 'Ğedit', 'Ğfind', 'Ğfindall', 'Ğfindany', 'Ğlayoutnames', 'Ğnew', 'Ğprocess', 'Ğscriptnames', 'Ğview');
$charArray = array(chr(37),chr(38),chr(39),chr(40),chr(41),chr(42),chr(43),chr(44),chr(45),chr(46),chr(47),chr(48),chr(49),chr(50),chr(51),chr(52),chr(53),chr(54),chr(55),chr(56),chr(57),chr(58),chr(59),chr(60),chr(61),chr(62),chr(63),chr(64),chr(65),chr(66),chr(67),chr(68),chr(69),chr(70),chr(71),chr(72),chr(73),chr(74),chr(75),chr(76),chr(77),chr(78),chr(79),chr(80),chr(81),chr(82),chr(83),chr(84),chr(85),chr(86),chr(87),chr(88),chr(89),chr(90),chr(91),chr(93),chr(94),chr(95),chr(96),chr(97),chr(98),chr(99),chr(100),chr(101),chr(102),chr(103),chr(104),chr(105),chr(106),chr(107),chr(108),chr(109),chr(110),chr(111),chr(112),chr(113),chr(114),chr(115),chr(116),chr(117),chr(118),chr(119),chr(120),chr(121),chr(122),chr(123),chr(124),chr(125),chr(126),chr(127),chr(128),chr(129),chr(130),chr(131),chr(132),chr(133),chr(134),chr(135),chr(136),chr(137),chr(138),chr(139),chr(140),chr(141),chr(142),chr(143),chr(144),chr(145),chr(146),chr(147),chr(148),chr(149),chr(150),chr(151),chr(152),chr(153),chr(154),chr(155),chr(156),chr(157),chr(158),chr(159),chr(160),chr(161),chr(162),chr(163),chr(164),chr(165),chr(166),chr(167),chr(168),chr(169),chr(170),chr(171),chr(172),chr(173),chr(174),chr(175),chr(176),chr(177),chr(178),chr(179),chr(180),chr(181),chr(182),chr(183),chr(184),chr(185),chr(186),chr(187),chr(188),chr(189),chr(190),chr(191),chr(192),chr(193),chr(194),chr(195),chr(196),chr(197),chr(198),chr(199),chr(200),chr(201),chr(202),chr(203),chr(204),chr(205),chr(206),chr(207),chr(208),chr(209),chr(210),chr(211),chr(212),chr(213),chr(214),chr(215),chr(216),chr(217),chr(218),chr(219),chr(220),chr(221),chr(222),chr(223),chr(224),chr(225),chr(226),chr(227),chr(228),chr(229),chr(230),chr(231),chr(232),chr(233),chr(234),chr(235),chr(236),chr(237),chr(238),chr(239),chr(240),chr(241),chr(242),chr(243),chr(244),chr(245),chr(246),chr(247),chr(248),chr(249),chr(250),chr(251),chr(252),chr(253),chr(254),chr(255));
$numChars = count($charArray);
$userPass = '';
if (! isset($_GET['FXuser'])) {
    $_GET['FXuser'] = '';
}
if (! isset($_GET['FXpass'])) {
    $_GET['FXpass'] = '';
}

function generateKey ($keyLength)
{
    $tempKey = '';
    $tempNum = 0;

    for ($i = 0; $i < $keyLength; ++$i) {
        if (strlen($tempKey) > 0) {
            $tempKey .= '!';
        }
        do {
            $tempNum = rand(37, 255);
        } while ($tempNum == 92);
        $tempKey .= $tempNum;
    }
    return $tempKey;
}

function vignereEncryptURL ($targetString)
{
    global $encryptKey, $charArray, $numChars;

    $keyArray = explode('!', $encryptKey);
    $targetArray = preg_split('//', $targetString, -1, PREG_SPLIT_NO_EMPTY);
    $encryptedURL = '';

    if (count($targetArray) > count($keyArray)) {
        while (count($targetArray) > count($keyArray)) {
            $keyArray = array_merge($keyArray, $keyArray);
        }
    }
    for ($i = 0; $i < count($targetArray); ++$i) {
        $encryptedURL .= $charArray[((array_search(chr($keyArray[$i]), $charArray) + array_search($targetArray[$i], $charArray)) % $numChars)];
    }
    $encryptedURL = urlencode($encryptedURL);
    return $encryptedURL;
}

function vignereDecryptURL ($targetString)
{
    global $encryptKey, $charArray, $numChars;

    $keyArray = explode('!', $encryptKey);
    $targetArray = preg_split('//', $targetString, -1, PREG_SPLIT_NO_EMPTY);
    $decryptedURL = '';

    if (count($targetArray) > count($keyArray)) {
        while (count($targetArray) > count($keyArray)) {
            $keyArray = array_merge($keyArray, $keyArray);
        }
    }
    for ($i = 0; $i < count($targetArray); ++$i) {
        $decryptedURL .= $charArray[((($numChars + array_search($targetArray[$i], $charArray)) - array_search(chr($keyArray[$i]), $charArray)) % $numChars)];
    }
    return $decryptedURL;
}

if (isset($_GET['FXimage'])) {
    str_replace($forbiddenRequests, '', $_GET['FXimage']);  // this lines keeps individuals from using this file to proxy most FM requests
    $currentURL = vignereDecryptURL($_GET['FXimage']);
    if (substr_count($currentURL, '.jpg') > 0) {
        $imageType = 'jpeg';
    } elseif (substr_count($currentURL, '.gif') > 0) {
        $imageType = 'gif';
    } elseif (substr_count($currentURL, '.tif') > 0) {
        $imageType = 'tiff';
    } elseif (substr_count($currentURL, '.png') > 0) {
        $imageType = 'png';
    }
    if (substr($currentURL, 0, 1) != '/') {
        $currentURL =  '/' . $currentURL;
    }
    if ($webUN != '' || $webPW != '') {
        $userPass = "{$webUN}:{$webPW}@";
    } elseif ($_GET['FXuser'] != '' || $_GET['FXpass'] != '') {
        $userPass = "{$_GET['FXuser']}:{$_GET['FXpass']}@";
    } else {
        $userPass = '';
    }

    // I'm using sockets here since it appears that at time the connection between built in php functions and FileMaker 7 break down.
    $data = '';
    $dataDelimiter = "\r\n";
    $socketData = "GET {$currentURL} HTTP/1.0{$dataDelimiter}";
    $socketData .= "Authorization: Basic " . base64_encode("$webUN:$webPW") . "{$dataDelimiter}";
    $socketData .= "Accept: */*{$dataDelimiter}";
    $socketData .= "Accept-Language: en-us{$dataDelimiter}";
    $socketData .= "Host: {$serverIP}:{$webCompanionPort}{$dataDelimiter}";
    $socketData .= "User-Agent: Mozilla/5.0{$dataDelimiter}{$dataDelimiter}";

    $fp = fsockopen ($serverIP, $webCompanionPort);
    fputs ($fp, $socketData);
    while (!feof($fp)) {
        $data .= fgets($fp, 128);
    }
    fclose($fp);
    $pos = strpos($data, chr(13) . chr(10) . chr(13) . chr(10)); // the separation code
    $data = substr($data, $pos + 4) . "\r\n";
    header("Content-Type: image/{$imageType}");
    header("Content-Length: " . strlen($data));
    echo($data);
}
?>