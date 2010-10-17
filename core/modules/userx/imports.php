<?php

/**
 * Permissions Aware URL generation.
 * Returns ?unauthorized if not authorized.
 * @see request\url()
 * @param string $local_url Local URL to convert.
 */
function pa_url($path, $get = null) {
    return \nmvc\userx\pa_url($path, $get);
}