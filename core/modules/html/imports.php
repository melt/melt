<?php

/** Escapes given string so it can be safely printed in HTML. */
function escape($string) {
    return \nanomvc\html\escape($string);
}