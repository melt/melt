<?php

/**
* @desc A model is a data set represented as a row in a transactional storage database.
*       Every model instance is represented by exactly one row in the database,
*       and every row in the database is represented by exactly one model instance.
*/
abstract class Model extends DataSet {
    /** @var array Cache of all fetched instances.  */
    private static $_instance_cache = array();

    /**
     * Allows quick creation of model copies.
     */
    public function __clone() {
        // Copies must be stored to be linked.
        $this->_id = -1;
    }

    /**
     * @desc Overidable event. Called on model instances before they are stored.
     * @param boolean $is_linked True if the model instance is currently linked in the database. False if it's about to be INSERTED.
     */
    public function beforeStore($is_linked) { }

    /**
     * @desc Overidable event. Called on model instances after they are stored.
     * @param boolean $is_linked True if the model instance was linked in the database before the store. False if it was INSERTED just now.
     */
    public function afterStore($was_linked) { }

    /**
     * @desc Overidable event. Called on model instances that is about to be unlinked in the database.
     */
    public function beforeUnlink() { }

    /**
     * @desc Overidable event. Called on model instances after they have been unlinked in the database.
     */
    public function afterUnlink() { }

    /**
    * @desc Creates a new unlinked model instance.
    * @return Model A new unlinked model instance.
    */
    public static function insert() {
        $name = get_called_class();
        $model = new $name(-1);
        return $model;
    }

    /**
    * @desc Stores any changes to this model instance to the database.
    * @desc If this is a new instance, it's inserted, otherwise, it's updated.
    */
    public function store() {
        $update_ex = null;
        if ($this->_id > 0) {
            // Updating existing row.
            $this->beforeStore(true);
            api_database::query($this->getUpdateSQL());
            $this->afterStore(true);
        } else {
            // Inserting.
            $this->beforeStore(false);
            api_database::query($this->getInsertSQL());
            $this->_id = api_database::insert_id();
            $this->afterStore(false);
        }
    }

    /**
    * @desc Unlinks this model instance from the database.
    */
    public function unlink() {
        // Can't unlink non linked instances.
        if ($this->_id < 1)
            return;
        static $called = false;
        if (!$called) {
            // Prevent a user abort from terminating the script during GC.
            ignore_user_abort(true);
            $called = true;
        }
        // Before unlink event.
        $this->beforeUnlink();
        // Gather information.
        $name = get_class($this);
        $table_name = api_string::cased_to_underline($name);
        $id = intval($this->_id);
        // Find all pointers that will get broken by this unlink and garbage collect.
        $broken_pointers = array();
        foreach (self::getPointerColumns($table_name) as $pointer) {
            list($child_model, $child_column) = $pointer;
            $instances = call_user_func(array($child_model, "selectWhere"), "$child_column = $id");
            // Garbage collect.
            foreach ($instances as $instance) {
                // Don't call GC on unlinked instances. gcPointer() may unlink recursivly.
                if ($instance->_id > 0)
                    $instance->gcPointer($child_column);
            }
        }
        // Do the actual unlink in database backend.
        $ret = api_database::query("DELETE FROM `" . _tblprefix . "$table_name` WHERE id = " . $id);
        unset(self::$_instance_cache[$name][$id]);
        $this->_id = -1;
        // After unlink event.
        $this->afterUnlink();
    }

    /**
     * @desc Returns an array of pointers (table,column) that points to given table.
     */
    private function getPointerColumns($table_name) {
        static $cache = array();
        if (isset($cache[$table_name]))
            return $cache[$table_name];
        $pointers = array();
        $result = api_database::query("SELECT child_table,child_column FROM `" . _tblprefix . "pointer_map` WHERE parent_table = \"$table_name\"");
        while (false !== ($row = api_database::next_array($result)))
            $pointers[] = array(api_string::underline_to_cased($row[0]), $row[1]);
        return $cache[$table_name] = $pointers;
    }
    
    private function getInsertSQL() {
        $name = get_class($this);
        $table_name = api_string::cased_to_underline($name);
        static $key_list_cache = array();
        if (!isset($key_list_cache[$table_name])) {
            $key_list = implode(',', Model::getColumnNames($name));
            $key_list_cache[$table_name] = $key_list;
        } else
            $key_list = $key_list_cache[$table_name];
        $value_list = array();
        foreach ($this->getColumns() as $colname => $column)
            $value_list[] = $column->getSQLValue();
        $value_list = implode(',', $value_list);
        return "INSERT INTO `"._tblprefix."$table_name` ($key_list) VALUES ($value_list)";
    }

    private function getUpdateSQL() {
        $table_name = api_string::cased_to_underline(get_class($this));
        $value_list = array();
        foreach ($this->getColumns() as $colname => $column)
            $value_list[] = "`$colname`=" . $column->getSQLValue();
        $value_list = implode(',', $value_list);
        $id = intval($this->_id);
        return "UPDATE `"._tblprefix."$table_name` SET $value_list WHERE id=$id";
    }

    /**
    * @desc Attempts to syncronize the layout of this model against the database table layout.
    */
    public static function syncLayout() {
        $name = get_called_class();
        $table_name = api_string::cased_to_underline($name);
        $model = new $name(-1);
        api_database::sync_table_layout_with_model($table_name, $model);
    }

    /**
    * @desc This function sets this model instance to the stored ID specified.
    * @return Model The model with the ID specified or FALSE.
    */
    public static function selectByID($id) {
        $id = intval($id);
        $name = get_called_class();
        $table_name = api_string::cased_to_underline($name);
        $model = self::touchModelInstance($table_name, $id);
        if ($model === false) {
            $id = intval($id);
            $model = new $name($id);
            $res = api_database::query("SELECT * FROM `"._tblprefix."$table_name` WHERE id = ".$id);
            if (api_database::get_num_rows($res) != 1)
                return false;
            $sql_row = api_database::next_assoc($res);
            $model = self::touchModelInstance($name, $id, $sql_row);
        }
        return $model;
    }

    /**
    * @desc Creates an array of model instances from a given sql result.
    */
    private static function makeArrayOf($name, $sql_result) {
        $length = api_database::get_num_rows($sql_result);
        if ($length == 0)
            return array();
        $array = array();
        for ($at = 0; $at < $length; $at++) {
            $sql_row = api_database::next_assoc($sql_result);
            $id = intval($sql_row['id']);
            $array[$id] = self::touchModelInstance($name, $id, $sql_row);
        }
        return $array;
    }

    /**
    * @desc Passing all sql result model instancing through this function to enable caching.
    * @param Mixed $sql_row Either a sql row result or null if function should return false instead of instancing model.
    */
    private static function touchModelInstance($name, $id, $sql_row = null) {
        if (isset(self::$_instance_cache[$name][$id]))
            return self::$_instance_cache[$name][$id];
        if ($sql_row === null)
            return false;
        $model = new $name($id);
        foreach ($model->getColumns() as $colname => $column)
            $column->set($sql_row[strtolower($colname)]);
        return self::$_instance_cache[$name][$id] = $model;
    }

    /**
    * @desc Returns an array of all children of the specified child model. (Instances that point to this instance.)
    *       Will throw an exception if the specified child model does not point to this model.
    * @desc For security reasons, use api_database::strfy() to escape and quote
    *       any strings you want to build your sql query from.
    * @param String $chold_model Name of the child model that points to this model.
    * @param String $where (WHERE xyz) If specified, any number of where conditionals to filter out rows.
    * @param Integer $offset (OFFSET xyz) The offset from the begining to select results from.
    * @param Integer $limit (LIMIT offset,xyz) If you want to limit the number of results, specify this.
    * @param String $order (ORDER BY xyz) Specify this to get the results in a certain order, like 'description ASC'.
    * @return Array An array of the selected model instances.
    */
    public final function selectChildren($child_model, $where = "", $offset = 0, $limit = 0, $order = "") {
        $ptr_fields = $this->getChildPointers($child_model);
        $id = $this->getID();
        // New models have no ID.
        if ($id <= 0)
            return array();
        $where = trim($where);
        if (strlen($where) > 0)
            $where = " AND $where";
        $where = "(" . implode(" = $id OR ", $ptr_fields) . " = $id)" . $where;
        return call_user_func(array($child_model, "selectWhere"), $where, $offset, $limit, $order);
    }

    /**
    * @desc Returns the number of children of the specified child model. (Instances that point to this instance.)
    *       Will throw an exception if the specified child model does not point to this model.
    * @desc For security reasons, use api_database::strfy() to escape and quote
    *       any strings you want to build your sql query from.
    * @param String $chold_model Name of the child model that points to this model.
    * @param String $where (WHERE xyz) If specified, any number of where conditionals to filter out rows.
    * @return Integer Count of child models.
    */
    public final function countChildren($child_model, $where = "") {
        $ptr_fields = $this->getChildPointers($child_model);
        $id = $this->getID();
        $where = trim($where);
        if (strlen($where) > 0)
            $where = " AND $where";
        $where = "(" . implode(" = $id OR ", $ptr_fields) . " = $id)" . $where;
        return call_user_func(array($child_model, "count"), $where);
    }

    /**
    * @desc Returns the name of the child pointer fields and validates the child.
    */
    private function getChildPointers($child_model) {
        $name = get_class($this);
        $table_name = api_string::cased_to_underline($name);
        $column_names = self::getColumnNames($child_model);
        $ptr_fields = array();
        foreach ($column_names as $colname) {
            $ptr_model_name = self::getPointerModelName($colname);
            if (strtolower($ptr_model_name) == $table_name)
                $ptr_fields[] = $colname;
        }
        if (count($ptr_fields) == 0)
            throw new Exception("Invalid child model: '$child_model'. Does not contain pointer(s) to the model '$name'. (Expected field(s) '" . $table_name . "_id[_...]' missing)");
        return $ptr_fields;
    }

    /**
    * @desc This function selects the first model instance that matches the specified $where clause.
    * @desc If there are no match, it returns FALSE.
    * @desc For security reasons, use api_database::strfy() to escape and quote
    * @desc any strings you want to build your sql query from.
    * @param String $where (WHERE xyz) If specified, any ammount of conditionals to filter out the row.
    * @param String $order (ORDER BY xyz) Specify this to get the results in a certain order, like 'description ASC'.
    * @desc Model The model instance that matches or FALSE if there are no match.
    */
    public static function selectFirst($where, $order = "") {
        $match = self::selectWhere($where, 0, 1, $order);
        if (count($match) == 0)
            return false;
        reset($match);
        return current($match);
    }

    /**
    * @desc This function returns an array of model instances that is selected by the given SQL arguments.
    * @desc For security reasons, use api_database::strfy() to escape and quote
    * @desc any strings you want to build your sql query from.
    * @param String $where (WHERE xyz) If specified, any number of where conditionals to filter out rows.
    * @param Integer $offset (OFFSET xyz) The offset from the begining to select results from.
    * @param Integer $limit (LIMIT offset,xyz) If you want to limit the number of results, specify this.
    * @param String $order (ORDER BY xyz) Specify this to get the results in a certain order, like 'description ASC'.
    * @desc Array An array of the selected model instances.
    */
    public static function selectWhere($where = "", $offset = 0, $limit = 0, $order = "") {
        $name = get_called_class();
        $table_name = api_string::cased_to_underline($name);
        $offset = intval($offset);
        $limit = intval($limit);
        if ($where != "")
            $where = " WHERE ".$where;
        if ($limit != 0)
            $limit = " LIMIT ".$offset.",".$limit;
        else if ($offset != 0)
            $limit = " OFFSET ".$offset;
        else $limit = "";
        if ($order != "")
            $order = " ORDER BY ".$order;
        $sql_result = api_database::query("SELECT * FROM `"._tblprefix."$table_name`".$where.$order.$limit);
        return self::makeArrayOf($name, $sql_result);
    }

    /**
    * @desc This function returns an array of model instances that matches the given SQL commands.
    * @desc For security reasons, use api_database::strfy() to escape and quote
    * @desc any strings you want to build your sql query from.
    * @param String $sqldata SQL command(s) that will be appended after the SELECT query for free selection.
    * @desc Array An array of the selected model instances.
    */
    public static function selectFreely($sqldata) {
        $name = get_called_class();
        $table_name = api_string::cased_to_underline($name);
        $sql_result = api_database::query("SELECT * FROM `"._tblprefix."$table_name` ".$sqldata);
        return self::makeArrayOf($name, $sql_result);
    }

    /**
    * @desc This function unlinks the model instance with the given ID.
    */
    public static function unlinkByID($id) {
        $instance = forward_static_call(array("Model", "selectByID"), $id);
        if ($instance !== false)
            $instance->unlink();
    }
    /**
    * @desc This function unlinks the selection of instances that matches the given SQL commands.
    * @desc For security reasons, use api_database::strfy() to escape and quote
    * @desc any strings you want to build your SQL query from.
    * @param String $sqldata SQL command(s) that will be appended after the DELETE query for free selection.
    */
    public static function unlinkFreely($sqldata) {
        $instances = forward_static_call(array("Model", "selectFreely"), $sqldata);
        foreach ($instances as $instance)
            $instance->unlink();
    }

    /**
    * @desc This function removes the selection of fields that matches the given SQL arguments.
    * @desc For security reasons, use api_database::strfy() to escape and quote
    * @desc any strings you want to build your sql query from.
    * @param String $where (WHERE xyz) If specified, any number of where conditionals to filter out rows.
    * @param Integer $offset (OFFSET xyz) The offset from the begining to select results from.
    * @param Integer $limit (LIMIT offset,xyz) If you want to limit the number of results, specify this.
    */
    public static function unlinkWhere($where = "", $offset = 0, $limit = 0) {
        $instances = forward_static_call(array("Model", "selectWhere"), $where, $offset, $limit);
        foreach ($instances as $instance)
            $instance->unlink();
    }

    /**
    * @desc Removes all children of the specified child model. (Instances that point to this instance.)
    *       Will throw an exception if the specified child model does not point to this model.
    * @desc For security reasons, use api_database::strfy() to escape and quote
    *       any strings you want to build your sql query from.
    * @param String $chold_model Name of the child model that points to this model.
    * @param String $where (WHERE xyz) If specified, any number of where conditionals to filter out rows.
    * @param Integer $offset (OFFSET xyz) The offset from the begining to unlink results from.
    * @param Integer $limit (LIMIT offset,xyz) If you want to limit the number of results, specify this.
    */
    public final function unlinkChildren($child_model, $where = "", $offset = 0, $limit = 0) {
        $instances = $this->selectChildren($child_model, $where, $offset, $limit);
        foreach ($instances as $instance)
            $instance->unlink();
    }

    /**
    * @desc This function counts the number of model instances that matches given SQL arguments.
    * @desc For security reasons, use api_database::strfy() to escape and quote
    * @desc any strings you want to build your sql query from.
    * @param String $where (WHERE xyz) If specified, any number of where conditionals to filter out rows.
    * @return Integer Number of matched rows.
    */
    public static function count($where = "") {
        $table_name = api_string::cased_to_underline(get_called_class());
        if ($where != "")
            $where = " WHERE ".$where;
        $result = api_database::query("SELECT COUNT(*) FROM `"._tblprefix."$table_name`".$where);
        $rows = api_database::next_array($result);
        return intval($rows[0]);
    }

    /**
    * @desc This function enlists a number of items in an orderable fashion that is controlled via specifyers (GET variable names).
    * @desc For security reasons, use api_database::strfy() to escape and quote
    * @desc any strings you want to build your sql query from.
    * @param String $where (WHERE xyz) If specified, any number of where conditionals to filter out rows.
    * @param Integer $items_per_page The number of items per page.
    * @param String $page_specifyer The name of the GET variable that specifies the page we´re currently on.
    * @param String $order_column_specifyer The name of the GET variable that specifies the column used for ordering.
    * @param String $order_specifyer The name of the GET variable that specifies the order used in ordering.
    * @param Array $limit_ordering_to If you want to limit the possible column names that can be ordered, specify them here.
    * @return Array All items listed on the current page.
    */
    public static function enlist_orderable($where = "", $items_per_page = 30, $page_specifyer = 'page', $order_column_specifyer = null, $order_specifyer = null, $limit_ordering_to = null) {
        $ordering = "";
        if ($order_column_specifyer != null) {
            $order_column = strval($_GET[$order_column_specifyer]);
            $columns = $this->getColumns();
            // A correct ordering column?
            if (($limit_ordering_to === null || in_array($order_column, $order_column_specifyer))
            && in_array($order_column, $columns)) {
                $order = strval($_GET[$order_specifyer]);
                $decending = (strtolower($order) == "desc");
                $ordering = $order_column . ($order_column == "DESC"? " DESC": " ASC");
            }
        }
        return forward_static_call(array("Model", "enlist_preordered"), $ordering, $decending? "DESC": "ASC", $items_per_page, $page_specifyer);
    }

    /**
     * This function enlist (paginates) a number of items. Useful when listing in views.
     * @desc For security reasons, use api_database::strfy() to escape and quote
     * @desc any strings you want to build your sql query from.
     * @param string $where (WHERE xyz) If specified, any number of where conditionals to filter out rows.
     * @param Integer $items_per_page The number of items per page.
     * @param String $page_specifyer The name of the GET variable that specifies the page we´re currently on.
     * @param string $ordering The column ordering. Eg. col1 asc, col2 desc.
     * @return Array All items listed on the current page.
     */
    public static function enlist($where = "", $items_per_page = 30, $page_specifyer = 'page', $ordering = "") {
        $total_count = forward_static_call(array("Model", 'count'), $where);
        $page_ub = ceil($total_count / $items_per_page) - 1;
        if ($page_ub < 0)
            $page_ub = 0;
        $page = intval(@$_GET[$page_specifyer]);
        if ($page > $page_ub)
            $page = $page_ub;
        else if ($page < 0)
            $page = 0;
        $items = forward_static_call(array("Model", 'selectWhere'), $where, $page * $items_per_page, $items_per_page, $ordering);
        self::$_last_enlist_page_ub = $page_ub;
        self::$_last_enlist_page_current = $page;
        return $items;
    }

    private static $_last_enlist_page_current = 0;
    private static $_last_enlist_page_ub = 0;

    /**
    * @desc Returns an simple but useful navigation array for the last enlistment.
    *       The navigation array consists of pagenumbers and triple dots that indicate number jumps.
    *       Tip: Use is_integer() to separate page numbers from tripple dot "jump" indicators.
    * @param integer $span Total pages to span to the left and to the right of the current page.
    * @return Array An array of pagenumbers and possible, tripple dots if span is to small to cover all pages.
    */
    public static function enlist_navigation($span = 6) {
        $page_current = intval(self::$_last_enlist_page_current);
        $page_ub = intval(self::$_last_enlist_page_ub);
        $nav = array(0);
        $start = $page_current - $span;
        if ($start <= 0)
            $start = 1;
        if ($start > 1)
            $nav[] = "...";
        for ($at = $start; $at <= ($page_current + $span) && $at <= $page_ub; $at++)
            $nav[] = $at;
        if ($at < $page_ub - 1) {
            $nav[] = "...";
            $nav[] = $page_ub;
        }
        return $nav;
    }

    protected final function getInterfaceDataSetAndAction($mif_name, $mif_id, $mif_redirect, $mif_delete_redirect) {
        // Insert or update?
        if ($mif_id > 0) {
            $model = call_user_func(array($mif_name, 'selectByID'), $mif_id);
            $success_msg = __("Record was successfully updated.");
        } else {
            $model = call_user_func(array($mif_name, 'insert'));
            $success_msg = __("Record was successfully added.");
        }
        // Delete?
        if (isset($_POST['do_delete'])) {
            if ($model === false)
                return;
            if (!is_string($mif_delete_redirect) || strlen($mif_delete_redirect) == 0)
                Flash::doFlash(__("You don't have permissions to delete this record."), FLASH_BAD);
            else if ($mif_id <= 0)
                Flash::doFlash(__("Conflicting operation: Delete and Insert. Ignored query."), FLASH_BAD);
            else {
                $model->unlink();
                Flash::doFlashRedirect($mif_delete_redirect, __("Record removed as requested."), FLASH_GOOD);
            }
            return null;
        }
        if ($model === false) {
            Flash::doFlash(__("The record no longer exists!"), FLASH_BAD);
            return null;
        }
        return array($model, $success_msg);
    }
}

?>
