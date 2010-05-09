<?php

/**
* @desc Creates a local URL from given path.
* @param String $path A local path, eg /etc/lol.png
* @param Array $get An optional array of keys and values to point to in get part.
* @return String A clean, non relative, formated URL to local destination.
*/
function url($path, $get = null) {
    return \nmvc\request\url($path, $get);
}