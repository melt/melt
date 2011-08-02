<?php namespace melt\js;

const JSTREE_DEFAULT_ICON = "/static/cmod/js/jquery-jstree/folder.png";

/**
 * Outputs a linked tree from the given iterator.
 * The models on it's nodes is expected to have a getUrl function.
 * @param melt\core\ModelTree $iterator The tree iterator.
 * @param string $id ID of tree (if specified).
 * @param string $get_url_arg If specified, the first argument to getUrl().
 * @return string ID of tree.
 */
function jstree_write(\melt\core\ModelTree $tree, $get_url_arg = null, $selected = null, $theme_name = "default", $draggable = false) {
    if (count($tree->getBranch()) == 0)
        return;
    $id = "i" . \melt\string\random_alphanum_str();
    echo '<div class="jstree" id="' . $id . '">';
    echo '</div><script type="text/javascript">';
    echo '$("#' . $id . '").tree(';
    $types = array();
    $json_out = array(
        "data" => array(
            "type" => "json",
            "opts" => array(
                "static" => jstree_get_branch($tree, $get_url_arg, $types, $draggable)
            ),
        ),
        "selected" => jstree_model_to_id($selected),
        "ui" => array("theme_name" => $theme_name),
    );
    $types["default"]["icon"]["image"] = false;
    $json_out["types"] = $types;
    echo json_encode($json_out);
    echo ');</script>';
    return $id;
}

/**
 * Returns tree branch.
 * @param melt\core\ModelTree $iterator The tree iterator.
 * @param string $id ID of tree (if specified).
 * @param string $get_url_arg If specified, the first argument to getUrl().
 * @param array $types
 */
function jstree_get_branch(\melt\core\ModelTree $tree, $get_url_arg, &$types, $draggable) {
    $out = array();
    foreach ($tree->getBranch() as $node) {
        $node_data = array();
        $node_obj = $node->getNode();
        $node_model_id = jstree_model_to_model_id($node_obj);
        $url = $node_obj->getUrl($get_url_arg);
        $node_data["attributes"]["id"] = $id = jstree_model_to_id($node_obj);
        $node_data["attributes"]["rel"] = $node_model_id;
        $node_data["data"]["title"] = (string) $node_obj;
        $node_data["data"]["attributes"]["href"] = $url;
        if (!isset($types[$node_model_id])) {
            // Need to add this type of node.
            $icon = url(\melt\core\implementing($node_obj, 'melt\jquery\HasJsTreeIcon')? $node_obj->getJsTreeIcon(): JSTREE_DEFAULT_ICON);
            $types[$node_model_id] = array(
                "clickable" => true,
                "renameable" => false,
                "deleteable" => false,
                "creatable" => false,
                "draggable" => $draggable,
                "icon" => array("image" => $icon),
            );
        }
        if (count($node->getBranch()) > 0)
            $node_data["children"] = jstree_get_branch($node, $get_url_arg, $types, $draggable);
        $out[] = $node_data;
    }
    return $out;
}

/**
 * Returns a unique identifier for a certain model instance
 * that can be used in HTML/CSS.
 */
function jstree_model_to_id($model) {
    if ($model === null)
        return false;
    $cls_name = jstree_model_to_model_id($model);
    $cls_name = "_" . $model->getID() . "_" . $cls_name;
    return $cls_name;
}


/**
 * Returns a unique identifier for a certain mode
 * that can be used in HTML/CSS.
 */
function jstree_model_to_model_id($model) {
    if ($model === null)
        return false;
    $cls_name = get_class($model);
    $cls_name = \melt\string\cased_to_underline(basename(str_replace('\\', '/', $cls_name)));
    $cls_name = substr($cls_name, 0, -6);
    return $cls_name;
}