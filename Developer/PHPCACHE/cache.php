<?php
define("HAS_PHPCACHE", true);

assert(defined("MAX_CACHE_AGE"));
assert(defined("DEBUG"));

function cache_garbage_collect($Age = MAX_CACHE_AGE) {
    $CutoffTime = time() - $Age;
    mysql_query("DELETE FROM phpcache WHERE Created < FROM_UNIXTIME($CutoffTime)") or die(mysql_error());
    return mysql_affected_rows();
}

function get_url_cached($URL, $forceUpdate = false) {
    // Retrieves the contents of a URL. The retrieved data will be cached so that subsequent calls
    // will return much faster.
    //
    // Passing true for $forceUpdate will cause the remote content to be retrieved regardless of the
    // cache status
    //
    // Results are returned in an associative array with keys 'Body' and 'Headers'
    global $HTTP_HOST, $QUERY_STRING, $HTTP_REFERER, $HTTP_USER_AGENT;

    $results = array();

    $CachedURLQuery = mysql_query("SELECT UNIX_TIMESTAMP(Created) AS Created, Headers, Body FROM phpcache WHERE SourceURL = '$URL'") or die(mysql_error());

    if (!$forceUpdate && mysql_num_rows($CachedURLQuery) == 1) {
        $CachedURL = mysql_fetch_array($CachedURLQuery);
        if ((time() - $CachedURL["Created"]) < MAX_CACHE_AGE) {
            if (DEBUG) echo "<P>Returning cached version of <a href=\"$URL\">$URL</a></P>\n";
            return $CachedURL;
        }
    }

    $ParsedURL = parse_url($URL);
    // true added to avoid the header loading mechanism
    // Since we don't need the headers, using the PHP library function is a win
    if (true or $ParsedURL["scheme"] != "http") {
        if (DEBUG) error_log("phpcache: Asked to cache non-http url - '$URL'");
        $Headers = "";
        $Body = "";

        $urlfp = @fopen($URL, 'r');

        if (empty($urlfp)) {
            error_log("phpcache: error loading $URL");
            return;
        }

        while ($line_in = fread($urlfp, 4096)) {
            $Body .= $line_in;
        }

        fclose($urlfp);

    } else {
        // TODO: rewrite this to use cURL extension

        $Headers = "";
        $Body = "";
        $host = $ParsedURL["host"];

        if (DEBUG) error_log("Connecting to $host\n");

        $host_sock = fsockopen($host, 80, $errno, $errstr) or die("$errstr ($errno)");

        // Send the request
        fputs($host_sock, "GET $URL HTTP/1.0\r\n");
        fputs($host_sock, "Host: $host\r\n");
        if (isset($HTTP_REFERER)) fputs($host_sock, "Referer: $HTTP_REFERER\r\n");
        if (isset($HTTP_USER_AGENT)) fputs($host_sock, "User-Agent: $HTTP_USER_AGENT\r\n");
        fputs($host_sock, "\r\n");

        if (DEBUG) error_log("Requested $URL\n");

        // Send HTTP headers, which will be everything we get back from the web server until the first blank line.
        socket_set_blocking($host_sock, false);
        while (!feof($host_sock)) {
            $header_line = fgets($host_sock, 8192);
            assert($header_line != false);

            if (chop($header_line) == "") {
                break;
            } else {
                $Headers .= $header_line;
            }
        }

        assert(!feof($host_sock));

        while ($line_in = fread($host_sock, 4096)) {
            $Body .= $line_in;
        }

        fclose($host_sock);
    }

    // Get what we're going to return this time
    $results["Headers"] = $Headers;
    $results["Body"] = $Body;

    // Store the results for later
    cache_url_data($URL, $Headers, $Body);

    return $results;
}

function cache_url_data($URL, $Headers, $Body) {
    // Stores a URL and the data associated with it for later usage
    $Headers = addslashes($Headers);
    $Body = addslashes($Body);
    mysql_query("DELETE FROM phpcache WHERE SourceURL='$URL'");
    mysql_query("INSERT INTO phpcache (SourceURL, Headers, Body) VALUES ('$URL', '$Headers', '$Body')") or die(mysql_error());
    return true;
}

function get_url_if_cached($URL) {
    // Similar to get_url_cache() but returns false if the URL is not already in the cache

    $CutoffTime = time() - MAX_CACHE_AGE;

    $CachedURLQuery = mysql_query("SELECT UNIX_TIMESTAMP(Created) AS Created, Headers, Body FROM phpcache WHERE SourceURL = '$URL' AND Created > FROM_UNIXTIME($CutoffTime)") or die(mysql_error());

    if (mysql_num_rows($CachedURLQuery) == 1) {
        $CachedURL = mysql_fetch_assoc($CachedURLQuery);
        return $CachedURL;
    }
    return false;
}
?>
