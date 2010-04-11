<?php

/**
 * Human language wrapper function for translations.
 * @see  sprintf()
 */
function __() {
    return call_user_func_array("nanomvc\\translate\\__", func_get_args());
}

