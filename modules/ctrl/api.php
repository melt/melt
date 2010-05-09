<?php namespace nmvc\ctrl;

function get_admin_menu() {
    static $menu_tree = null;
    if (is_array($menu_tree))
        return $menu_tree;
    // Transform the categories and items into a single "pre tree".
    $menu_pre_tree = array();
    $admin_menu_items = \nmvc\core\require_shared_data("admin_menu_items");
    foreach ($admin_menu_items as $module_name => $module_menu_items)
    foreach ($module_menu_items as $category_id => $admin_menu_category) {
        if (isset($admin_menu_category["category"])
        && isset($admin_menu_category["icon"])) {
            // Declare a new category.
            $menu_pre_tree[$category_id]["category"] = $admin_menu_category["category"];
            $menu_pre_tree[$category_id]["icon"] = $admin_menu_category["icon"];
            $menu_pre_tree[$category_id]["weight"] = intval(@$admin_menu_category["weight"]);
        }
        if (isset($admin_menu_category["paths"])) {
            $paths = $admin_menu_category["paths"];
            if (isset($menu_pre_tree[$category_id]["paths"])) {
                $menu_pre_tree[$category_id]["paths"] = array_merge(
                    $menu_pre_tree[$category_id]["paths"],
                    $paths
                );
            } else
                $menu_pre_tree[$category_id]["paths"] = $paths;
        }
    }
    // Only keep fully declared categories with items in them.
    $menu_tree = array();
    foreach ($menu_pre_tree as $id => $pre_category) {
        if (!isset($pre_category["category"])
        || !isset($pre_category["icon"])
        || !isset($pre_category["paths"])
        || count($pre_category["paths"]) == 0)
            continue;
        $menu_tree[$id] = $pre_category;
    }
    // Sort tree before returning.
    uasort($menu_tree, create_function('$a, $b', 'return $a["weight"] > $b["weight"];'));
    return $menu_tree;
}

/** Configures a wyswiyg. */
function configure_wysiwyg(\nmvc\tinymce\WysiwygType $wysiwyg) {
    $config = CtrlSettingsModel::get();
    $config_classes = array(
        "simple" => "/tinymce/config_simple",
        "advanced" =>  "/ctrl/config_advanced",
    );
    $wysiwyg->config_class = $config_classes[$config->wysiwyg_type];
}