<?php namespace nanomvc\nano_cms;

class AdminController extends \nanomvc\Controller {
    public function beforeFilter() {
        // Using Ctrl Administration Panel Module.
        \nanomvc\core\require_module("ctrl");
    }

    public function pages($edit_id = null, $new_type = null, $new_parent = null) {
        // Get the editing site node.
        $this->site_node = null;
        if ($edit_id == "new") {
            $new_node_name = \nanomvc\string\base64_alphanum_decode($new_type);
            $possible_pages = get_dynamic_pages();
            if (!isset($possible_pages[$new_node_name]))
                \nanomvc\request\show_404();
            $this->page_type = $new_node_name;
            $this->site_node = $new_node_name::insert();
            $this->site_node->parent_id = SiteNodeModel::selectByID($new_parent);
        } else if ($edit_id !== null) {
            $id = intval($edit_id);
            $this->site_node = SiteNodeModel::selectByID($id);
        }
        // Determine the selected site node.
        $this->selected_site_node = null;
        if ($this->site_node !== null)
            $this->selected_site_node = $this->site_node->isLinked()? $this->site_node: $this->site_node->parent_id;
        // Construct the page tree.
        $this->page_tree = get_page_tree();
    }

    /** AJAX callback to move site nodes. */
    public function move_site_node() {
        header("Content-Type: application/json");
        $node = $_POST["node"];
        $ref_node = $_POST["ref_node"];
        $type = strtolower($_POST["type"]);
        // Convert the node ids to true site node id's.
        $node = intval(substr($node, 1));
        $ref_node = intval(substr($ref_node, 1));
        // Get the nodes.
        $node = SiteNodeModel::selectByID($node);
        $ref_node = SiteNodeModel::selectByID($ref_node);
        if (!is_object($node) || !is_object($ref_node))
            die(json_encode(false));
        // The only thing important is the new parent. Not the position.
        if ($type != "inside")
            $ref_node = $ref_node->parent_id;
        // The new parent cannot be the existing parent.
        // This can give the illusion that it's possible to move them around
        // on branches.
        if ($ref_node === $node->parent_id)
            die(json_encode(false));
        // Make sure the new parent is not itself or a child. That would break the tree.
        $grandparent = $ref_node;
        $i = 0;
        while (is_object($grandparent)) {
            if ($grandparent === $node)
                die(json_encode(false));
            else if ($i++ > 10)
                // Should never happen in normal conditions but test to prevent LOD.
                die(json_encode(false));
            $grandparent = $grandparent->parent_id;
        }
        // Set the new parent and return ok.
        $node->move($ref_node);
        $node->store();
        die(json_encode(true));
    }

}
