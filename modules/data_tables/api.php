<?php namespace nanomvc\data_tables;

function include_dt() {
    static $included = false;
    if ($included)
        return;
    if (!\nanomvc\core\module_loaded("Jquery"))
        trigger_error("DataTables requires the Jquery module!", \E_USER_ERROR);
    \nanomvc\View::render("/data_tables/include", null, false, true);
    $included = true;
}

/**
 * Creates a table at this location that enlists the given model.
 * DataTablesListable will also give you the option to give columns better values.
 * @param string $model_name Shortened classname of the model that should be listed. (The Xyz in nanomvc\XyzModel)
 * @param string $view_url Url where each item can be viewed separatly. It will have the ID appended to it.
 * @param string $where A conditional SQL where filter that limits the instances listed.
 * @param array $columns The columns that will be used. If null, the getEnlistColumns() return value will be used instead.
 * @param boolean $delete_batch_operation Set to true to generate a delete batch operation.
 * @param array $batch_operations An array of additional batch operations in the form of action urls mapped to action labels.
 * @see DataTablesListable
 * @return string ID handle to the list.
 */
function list_model($model_name, $view_url, $insert_url = null, $where = null, $columns = null, $delete_batch_operation = false, $batch_operations = array()) {
    $class_name = "nanomvc\\" . $model_name . "Model";
    if (!class_exists($class_name) || !is_subclass_of($class_name, 'nanomvc\Model'))
        trigger_error("$class_name is not a valid model!", \E_USER_ERROR);
    if ($columns === null) {
        $interfaces = class_implements($class_name);
        if (!isset($interfaces['nanomvc\data_tables\DataTablesListable']))
            trigger_error("Did not specify columns and $class_name does not implement DataTablesListable!", \E_USER_ERROR);
        $columns = $class_name::getEnlistColumns();
    }
    // Verifies columns.
    if (!is_array($columns) || count($columns) == 0)
        trigger_error("No columns specified!", \E_USER_ERROR);
    $column_names = \nanomvc\Model::getColumnNames($class_name);
    foreach ($columns as $column_name => $column_label) {
        if (!isset($column_names[$column_name]))
            trigger_error("Column name '$column_name' does not exist in $class_name!", \E_USER_ERROR);
    }
    // Creates a delete batch operation if it should.
    if ($delete_batch_operation) {
        $batch_operations["/data_tables/action/delete_batch"] = "!Delete Permanently";
    }
    // Generates url that can be used to get data.
    $data = serialize(array($class_name, $where, array_keys($column_names)));
    $data_url = url("/data_tables/action/ajax_callback/" . \nanomvc\string\simple_crypt($data));
    $controller = new \nanomvc\Controller();
    $controller->columns = $columns;
    $controller->data_url = $data_url;
    $controller->view_url = $view_url;
    $controller->batch_ops = $batch_operations;
    $controller->insert_url = $insert_url;
    $controller->batch_data = \nanomvc\string\simple_crypt(serialize(array($class_name, $where)));
    $controller->id = "dt" . \nanomvc\string\random_hex_str(10);
    \nanomvc\View::render("/data_tables/table", $controller, false, true);
    return $controller->id;
}

/**
 * Will return an array like: (class_name, where, ids).
 * class_name: Model to operate on.
 * where: Where argument passed to list_model to filter instances.
 * ids: Array of ID's. Note that the ID's is only guaranteed to be integers.
 * They are not cleaned in any other way.
 * Request will fail if the data is invalid.
 * @return array
 */
function get_batch_op_data() {
    if (!isset($_POST['data']) || !isset($_POST['ids']))
        \nanomvc\request\show_invalid("Data missing");
    $data = $_POST['data'];
    $ids = explode(",", $_POST['ids']);
    if (count($ids) == 0)
        \nanomvc\request\show_xyz(403);
    foreach ($ids as &$id)
        $id = intval($id);
    $data = \nanomvc\string\simple_decrypt($data);
    if ($data === false)
        \nanomvc\request\show_xyz(403);
    list($class_name, $where) = unserialize($data);
    return array($class_name, $where, $ids);
}


/**
 * Will insert one or more lines of javascript that refreshes the table
 * identified by it's handle.
 */
function js_table_refresh($table_handle) {
    echo "data_table_$table_handle.fnDraw();";
}