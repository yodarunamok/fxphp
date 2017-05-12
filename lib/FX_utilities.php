<?php
/**
 * Created by IntelliJ IDEA.
 * User: Chris Hansen (chris@iviking.org)
 * Date: 6/13/15
 * Time: 9:54 AM
 */

/**
 * Function to format dates moved from server_data.php.  Likely a better way to do this in PHP...
 *
 * @param $cD   integer     Numeric day of month
 * @param $cM   integer     Numeric month number (1-12)
 * @param $cY   integer     Four digit year
 * @return string
 */
function fmdate( $cD, $cM, $cY ) {
    return substr( '00' . $cM, -2 ) . '/' . substr( '00' . $cD, -2 ) . '/' . $cY;
}
