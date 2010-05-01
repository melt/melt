<?php namespace nanomvc\url_mapper;

/**
 * Returns true if the requested alias is not reserved by url_map.
 * Note that this doesn't imply that the alias is availible.
 * It might still be hidden behind a module or controller.
 * @return boolean
 */
function alias_reserved($requested_alias) {
    return UrlMapModel::count("url_alias = " . strfy($requested_alias)) > 0;
}


/**
 * Adds an invoke alias with the requested alias to the specified invoke path.
 * The alias might not be availible, in which case this function returns a
 * similar one.
 * @param string $requested_alias The alias requested.
 * @param string $invoke_path The internal path that is required to be invoked.
 * @return UrlMapModel URL mapping given.
 */
function add_invoke_alias($requested_alias, $invoke_path) {
    // TODO: Make it possible to override any existing alias.
    // Alias cannot match module, any controller and may not be taken.
    $modules = \nanomvc\internal\get_all_modules();
    $requested_alias = url_tokenify($requested_alias);
    // Forward slash is allowed.
    $requested_alias = str_replace(array("%2f", "%2F"), "/", $requested_alias);
    // Strip forward slash prefixes and tails.
    $requested_alias = trim($requested_alias, "/");
    // Replace empty aliases with random ones.
    if ($requested_alias == "")
        $requested_alias = \nanomvc\string\random_alphanum_str(8);
    // Require all reserved url aliases.
    $reserved_url_aliases = \nanomvc\core\require_shared_data("reserved_url_aliases");
    $count = 0;
    // Critical section when looking for collisions and inserting.
    UrlMapModel::lock();
    // Prepare first part of URL.
    $first_part_end = strpos($requested_alias, "/");
    if ($first_part_end !== false)
        $alias_first_part = substr($requested_alias, 0, $first_part_end);
    while (true) {
        $count++;
        if ($count == 1)
            $attempt_alias = $requested_alias;
        else if ($count < 4)
            $attempt_alias = $requested_alias . "-" . $count;
        else if ($count < 8)
            $attempt_alias = $requested_alias . "-" . dechex(mt_rand(7, 0xfff));
        else
            $attempt_alias = $requested_alias . "-" . dechex(mt_rand(7, 0xfffffff));
        // Get the first part of the url if testing single parted url.
        if ($first_part_end === false)
            $alias_first_part = $attempt_alias;
        // Collision detector.
        if (isset($modules[$alias_first_part])
        || \nanomvc\Controller::pathToController("/$alias_first_part/a", true) !== false
        || alias_reserved($requested_alias))
            continue;
        // Also cannot be reserved by any other module.
        foreach ($reserved_url_aliases as $module_aliases)
        foreach ($module_aliases as $reserved_alias)
            if (strtolower($reserved_alias) == $attempt_alias)
                continue;
        // Alias availible.
        break;
    }
    $url_map = UrlMapModel::insert();
    $url_map->url_alias = $attempt_alias;
    $url_map->invoke = $invoke_path;
    $url_map->store();
    // Exiting critical section.
    \nanomvc\db\unlock();
    return $url_map;
}

/**
 * Takes a string and turns it into a good-looking human friendly url token.
 * What it prevents:
 * - Super large tokens (32 characher limit)
 * - No ascii control charachers
 * - No whitespace (replaces with dash)
 * - Url encodes special charachers that can break URLs.
 * @param string $url_token
 * @return string
 */
function url_tokenify($url_token) {
    // Make requested alias more URL-ish (not to long, no whitespace, etc)
    $url_token = iconv_substr(strtolower($url_token), 0, 32);
    // Remove ascii control charachers. (Yes, this is valid in UTF-8)
    $url_token = preg_replace('#[\x00-\x1F\x7F]#', '', $url_token);
    // Replace whitespace with dash.
    $url_token = preg_replace('#[\s]#', "-", $url_token);
    // Urlencode all special charachers.
    return urlencode($url_token);
}

/**
 * Removes specified invoke alias.
 */
function remove_invoke_alias($alias) {
    UrlMap::unlinkWhere("alias = " . strfy($alias));
}

/* TODO: Write a function that migrates all aliases to a new environment
 * where there are potential new reserved blocking aliases. */