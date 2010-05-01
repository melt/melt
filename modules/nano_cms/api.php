<?php namespace nanomvc\nano_cms;

/**
 * Returns the dynamic page class names mapped to their friendly name
 * in an array.
 */
function get_dynamic_pages() {
    $dynamic_pages = array();
    foreach (\nanomvc\core\require_shared_data("dynamic_pages") as $dpage_mod_array)
    foreach ($dpage_mod_array as $dynamic_page_class_name) {
        $page_type_name = $dynamic_page_class_name::getPageTypeName();
        $dynamic_pages[$dynamic_page_class_name] = $page_type_name;
    }
    return $dynamic_pages;
}

/**
 * Returns a breadcrumbs array of url tokens mapped to page titles.
 * @return array
 */
function get_breadcrumbs() {
    $url_map = UrlMapperModule::$url_map;
    if (!\nanomvc\is($url_map, '\nanomvc\nano_cms\SiteNodeModel'))
        // Outside breadcrumbs.
        return array();
    $out = array();
    while ($url_map !== null) {
        $out[$url_map->getURL()] = $url_map->title;
        $url_map = $url_map->parent;
    }
    return array_reverse($out, true);
}

function get_page_tree() {
    return \nanomvc\core\ModelTree::makeFromModel(
        array(
            'nanomvc\nano_cms\SiteNodeModel' => 'parent_id',
        ),
        array(),
        array(
            'nanomvc\nano_cms\SiteNodeModel' => 'title ASC',
        )
    );
}

/**
 * Prints the site menu.
 * @param $sub_tree mixed Only used internally, when recursing.
 */
function print_site_menu($branch = null) {
    if ($branch === null) {
        $page_tree = get_page_tree();
        $branch = $page_tree->getBranch();
        $menu_items = \nanomvc\core\require_shared_data("menu_items");
        foreach ($menu_items as $module_menu_items)
        foreach ($module_menu_items as $menu_key => $menu_value)
            $branch[$menu_key] = $menu_value;
    }
    if (count($branch) == 0)
        return;
    echo "<ul>";
    foreach ($branch as $key => $value) {
        if (is($value, '\nanomvc\core\ModelTree')) {
            $node = $sub_tree->getNode();
            $sub_tree = $value;
            $url = $node->getURL();
            $title = escape($node->title);
        } else if (is_array($value)) {
            $sub_tree = $value;
            $url = null;
            $title = $key;
        } else if (is_string($key) && is_string($value)) {
            $sub_tree = null;
            $url = $value;
            $title = $key;
        }
        echo "<li><a href=\"$url\">$title</a></li>";
        if (is_array($sub_tree))
            print_site_menu($sub_tree);
    }
    echo "</ul>";
}