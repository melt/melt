<?php namespace nanomvc\url_mapper;

/**
 * Returns true if the requested alias is not reserved by url_map.
 * Note that this doesn't imply that the alias is availible.
 * It might still be hidden behind a module or controller.
 * @return boolean
 */
function alias_reserved($requested_alias) {
    return UrlMap::count("alias = " . strfy($requested_alias)) > 0;
}


/**
 * Adds an invoke alias with the requested alias to the specified invoke path.
 * The alias might not be availible, in which case this function returns a
 * similar one.
 * @param string $requested_alias The alias requested.
 * @param string $invoke_path The internal path that is required to be invoked.
 * @param boolean $ref If set to TRUE, the url map instance will be returned
 * instead of the actual alias given.
 * @return string Actual alias given.
 */
function add_invoke_alias($requested_alias, $invoke_path, $ref = false) {
    // Alias cannot match module, any controller and may not be taken.
    $modules = internal\get_all_modules();
    if ($requested_alias == "") {
        // Replace empty aliases with random ones.
        $requested_alias = \nanomvc\string\random_alphanum_str(8);
    } else {
        // Make requested alias more URL-ish (not to long, no whitespace, etc)
        $requested_alias = substr($requested_alias, 0, 24);
        $requested_alias = preg_replace("#[\s/]#", "-", substr($requested_alias));
    }
    // Require all reserved url aliases.
    $reserved_url_aliases = nanomvc\core\require_shared_data("reserved_url_aliases");
    $count = 0;
    // Critical section when looking for collisions and inserting.
    UrlMap::lock();
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
        // Collision detector. A collision is not critical as url_mapper uses
        // request catching. The object will simply not be visible.
        if (isset($modules[$attempt_alias])
        || \nanomvc\Controller::pathToController("/$attempt_alias/a", true) !== false
        || UrlMap::count("alias = " . strfy($attempt_alias)) > 0)
            continue;
        // Also cannot be reserved by any other module.
        foreach ($callback_modules as $module)
            if ($module::aliasTakenCallback($attempt_alias))
                continue;
        // Alias availible.
        break;
    }
    $url_map = UrlMap::insert();
    $url_map->alias = $attempt_alias;
    $url_map->invoke = $invoke_path;
    $url_map->store();
    // Exiting critical section.
    \nanomvc\db\unlock();
    return $ref? $url_map: $attempt_alias;
}

/**
 * Removes specified invoke alias.
 */
function remove_invoke_alias($alias) {
    UrlMap::unlinkWhere("alias = " . strfy($alias));
}

/* TODO: Write a function that migrates all aliases to a new environment
 * where there are potential new reserved blocking aliases. */