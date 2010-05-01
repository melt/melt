<?php namespace nanomvc\data_tables;

class ActionController extends \nanomvc\Controller {
    function ajax_callback($data) {
        $data = \nanomvc\string\simple_decrypt($data);
        if ($data === false)
            \nanomvc\request\show_invalid();
        list($class_name, $base_where, $column_names) = unserialize($data);
        // Determine filtering.
        $search = $_GET['sSearch'];
        // A maximum of 8 search terms is allowed.
        $search_terms = preg_split("#[ ][ ]*#", trim($search), 8);
        $search_where = array();
        foreach ($search_terms as $term) {
            // Escape mySQL wildcards.
            $term = preg_replace("([_%])", '\\\1', $term);
            $term = " LIKE " . strfy("%$term%");
            $term_where = array();
            foreach ($column_names as $column_name)
                $term_where[] = $column_name . $term;
            $search_where[] = implode(" OR ", $term_where);
        }
        // ALL search terms SHOULD match SOMEWHERE.
        $where = (strlen($base_where) > 0? $base_where . " AND ": null)
        . "(" . implode(") AND (", $search_where) . ")";
        // Determine offset and limit.
        $offset = intval($_GET['iDisplayStart']);
        $limit = intval($_GET['iDisplayLength']);
        if ($limit > 100)
            $limit = 100;
        else if ($limit < 0)
            $limit = 0;
        // Determine sorting order.
        $order = array();
        $used_cols = array();
        for ($i = 0; ; $i++) {
            // Iterate trough all sorting conditions.
            $col_id_key = "iSortCol_" . $i;
            $sort_dir_key = "sSortDir_" . $i;
            if (!isset($_GET[$col_id_key]))
                break;
            // Get column id (must exist).
            $col_id = intval($_GET[$col_id_key]) - 2;
            if (!isset($column_names[$col_id]))
                continue;
            // No sorting same column twice.
            if (isset($used_cols[$col_id]))
                continue;
            $used_cols[$col_id] = $col_id;
            // Piece together this sorting condition.
            $col = $column_names[$col_id];
            $sort_dir = @$_GET[$sort_dir_key];
            $sort_dir = (strlen($sort_dir) > 0 && strtolower($sort_dir[0]) == "d")? "DESC": "ASC";
            $order[] = "$col $sort_dir";
        }
        $order = implode(", ", $order);
        // Do the selection.
        $instances = $class_name::selectWhere($where, $offset, $limit, $order);
        // Determine if it can use value_enlist.
        $interfaces = class_implements($class_name);
        $do_enlist_value = isset($interfaces['nanomvc\data_tables\DataTablesListable']);
        // Piece together the JS output.
        $output = '{';
	$output .= '"sEcho": ' . intval($_GET['sEcho']) . ', ';
	$output .= '"iTotalRecords": ' . $class_name::count($base_where) . ', ';
	$output .= '"iTotalDisplayRecords": ' . $class_name::count($where) .  ', ';
	$output .= '"aaData": [ ';
        $first_instance = true;
        foreach ($instances as $instance) {
            if ($first_instance)
                $first_instance = false;
            else
                $output .= ",";
            $output .= "[";
            if ($do_enlist_value)
                $enlist_values = $instance->getTableEnlistValues();
            $output .= '"", "' . $instance->getID() . '"';
            foreach ($column_names as $column_name) {
                $output .= ",";
                $value = isset($enlist_values[$column_name])? $enlist_values[$column_name]: $instance->{"§$column_name"}->view();
                $output .= '"' . addslashes($value) . '"';
            }
            $output .= "]";
        }
	$output .= '] }';
        // AJAX Response
        \nanomvc\request\reset();
        header("Content-Type: application/json");
        die($output);
    }

    function delete_batch() {
        list($class_name, $where, $ids) = get_batch_op_data();
        $ids = "(" . implode(",", $ids) . ")";
        $where = ((strlen($where) > 0)? "$where AND ": "") . " id IN $ids";
        $class_name::unlinkWhere($where);
        // AJAX Response
        \nanomvc\request\reset();
        die();
    }
}
