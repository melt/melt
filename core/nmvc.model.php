<?php

namespace nanomvc;

/**
 * nanoModel
 */
abstract class Model {
    /** @var int Identifier of this data set, only readable. */
    protected $_id;
    /** @var array Where columns are internally stored for assignment overload. */
    private $_cols;
    /** @var array Cache of all columns in this model. */
    private $_columns_cache = null;
    /** @var array Cache of all fetched instances.  */
    private static $_instance_cache = array();

    
    /** Returns the ID of this model instance or FALSE if not stored yet. */
    public final function getID() {
        return ($this->_id < 1)? false: $this->_id;
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
     * @desc Overidable function. Called on model instances when one of their pointers
     * is turning invalid because the instance that pointer points to is about
     * to be unlinked from the database.
     * The default implementation is to clear that pointer (set it to 0)
     * but this can be overridden to any particular garbage collection behavior.
     * @desc Note: Always clearing broken pointers to zero is useful because
     * you can find out if a pointer points nowhere with a simple == 0.
     * @desc This function is NOT called on instances that are currently beeing unlinked
     * in the stack. This is because GC is considered unneccessary on already
     * deleted instances. Also, it enables you to unlink any instances freely
     * in this function, in any model graph, without getting infinite loops.
     */
    public function gcPointer($field_name) {
        $this->$field_name = 0;
    }

    /**
     * @desc Override this function to implement application level model access control.
     */
    public function accessing() { }

    /**
    * @desc Override this function to initialize members of this model.
    */
    public function initialize() { }

    /**
    * @desc Verify that the specified classname exists and extends the parent.
    
    private static function existsAndExtends($location, $class_name, $parent) {
        if (!class_exists($class_name))
            trigger_error("'$location' is invalid: '$class_name' could not be found!", \E_USER_ERROR);
        if (!is_subclass_of($class_name, $parent))
            trigger_error("'$location' is invalid: '$class_name' was expected to extend the '$parent' class but didn't!", \E_USER_ERROR);
    }

    /**
    * @desc Returns the pointer model name from the column name or NULL if column is not of pointer type.
    *
    protected static function getPointerModelName($colname) {
        if (false !== ($pos = strpos($colname, "__"))) {
            $module_name = substr($colname, 0, $pos);
            $colname = substr($colname, $pos + 2);
        } else
            $module_name = null;
        $colname_parts = array_reverse(explode("_", $colname));
        $single_ptr = $colname_parts[0] == "id";
        $multi_ptr = !$single_ptr && $colname_parts[1] == "id";
        if ($single_ptr || $multi_ptr) {
            // Evaluate the pointer model name.
            unset($colname_parts[0]);
            if ($multi_ptr)
                unset($colname_parts[1]);
            $model_name = "";
            foreach (array_reverse($colname_parts) as $part)
                $model_name .= ucfirst($part);
            return "nanomvc\\" . (($module_name !== null)? $module_name . "\\" . $model_name: $model_name) . "Model";
        } else
            return null;
    }*/

    private static function preParseEscape($str) {
        return str_replace(array("\\\\", "\\,", "\\="), array("\x00", "\x01", "\x02"), $str);
    }

    private static function postParseUnescape($str) {
        return str_replace(array("\x00", "\x01", "\x02"), array("\\", ",", "="), $str);
    }

    /**
    * @desc Translates the field specifiers to type handler instances.
    */
    protected final function __construct($id) {
        $this->accessing();
        $model_name = get_class($this);
        static $parsed_model_cache = array();
        if (!isset($parsed_model_cache[$model_name])) {
            $parsed_col_array = array();
            $vars = get_class_vars($model_name);
            $columns = array();
            foreach ($vars as $column_name => $column_parameters) {
                // Ignore non column members.
                if ($column_name[0] == '_')
                    continue;
                // Tokenize attributes.
                $attributes = explode(",", self::preParseEscape($column_parameters));
                // Parse type class name.
                $type_class_name = self::postParseUnescape($attributes[0]);
                unset($attributes[0]);
                if ($type_class_name == "")
                    trigger_error("Invalid type: '$model_name.\$$column_name' has nothing specified in type field.", \E_USER_ERROR);
                $type_class_name = 'nanomvc\\' . $type_class_name . "Type";
                if (!class_exists($type_class_name)) {
                    trigger_error("Invalid model column: $model_name.\$$column_name - Type '$type_class_name' is undefined.", \E_USER_ERROR);
                } else if (is_subclass_of($type_class_name, 'nanomvc\Reference')) {
                    if (!string\ends_with($column_name, "_id"))
                        trigger_error("Invalid model column: $model_name.\$$column_name - nanoMVC name convention requires reference type columns to end with '_id'!", \E_USER_ERROR);
                    // Expects the type handler of this class to extend the special reference type.;
                    $pointer_target_class_name = $type_class_name::STATIC_TARGET_MODEL;
                    if ($pointer_target_class_name === null) {
                        // Using default parsing to determine target model (2nd argument).
                        $pointer_target_class_name = 'nanomvc\\' . self::postParseUnescape($attributes[1]) . 'Model';
                        unset($attributes[1]);
                        if (!class_exists($pointer_target_class_name) || !is_subclass_of($pointer_target_class_name, 'nanomvc\Model'))
                            trigger_error("Invalid model column: $model_name.\$$column_name - Reference target '$pointer_target_class_name' is undefined or not a nanomvc\\Model.", \E_USER_ERROR);
                    }
                    $type_handler = new $type_class_name($column_name, null, $pointer_target_class_name);
                } else if (is_subclass_of($type_class_name, 'nanomvc\Type')) {
                    if (string\ends_with($column_name, "_id"))
                        trigger_error("Invalid model column: $model_name.\$$column_name - nanoMVC name convention doesn't allow non-reference type columns to end with '_id'!", \E_USER_ERROR);
                    // Standard type handles must extend the type class.
                    $type_handler = new $type_class_name($column_name, null);
                } else
                    trigger_error("Invalid model column: $model_name.\$$column_name - The specified type '$type_class_name' is not a nanomvc\\Type.", \E_USER_ERROR);
                foreach ($attributes as $attribute) {
                    $eqp = strpos($attribute, "=");
                    if ($eqp === false)
                        trigger_error("Invalid model column: $model_name.\$$column_name - Attribute token '$attribute' lacks equal sign (=).", \E_USER_ERROR);
                    $key = self::postParseUnescape(substr($attribute, 0, $eqp));
                    $val = self::postParseUnescape(substr($attribute, $eqp + 1));
                    if (!property_exists(get_class($type_handler), $key))
                        trigger_error("Invalid model column: $model_name.\$$column_name - The type '$type_class_name' does not have an attribute named '$key'.", \E_USER_ERROR);
                    $type_handler->$key = $val;
                }
                // Cache this untouched type instance and clone it to other new instances.
                $parsed_col_array[$column_name] = $type_handler;
            }
            $parsed_model_cache[$model_name] = $parsed_col_array;
        } else
            $parsed_col_array = $parsed_model_cache[$model_name];
        // Copies all columns into this model.
        $this->_cols = $parsed_col_array;
        foreach ($this->_cols as $column_name => &$type_instance) {
            // Assignment overload.
            unset($this->$column_name);
            // Cloning parsed type instance and link myself.
            $type_instance = clone $type_instance;
            $type_instance->parent = $this;
        }
        $this->_id = intval($id);
        $this->initialize();
    }

    /** Assignment overloading. */
    public function __get($name) {
        $by_type_ref = $name[0] == "§";
        if ($by_type_ref)
            $name = substr($name, 1);
        if (!isset($this->_cols[$name])) {
            trigger_error("Trying to read non existing column '$name' on model '" . get_class($this) . "'.", \E_USER_NOTICE);
            return;
        }
        // Can read the type handler instance (for direct function calling etc).
        return $by_type_ref? $this->_cols[$name]: $this->_cols[$name]->get();
    }

    /** Assignment overloading. */
    public function __set($name,  $value) {
        if (!isset($this->_cols[$name])) {
            trigger_error("Trying to write to non existing column '$name' on model '" . get_class($this) . "'.", \E_USER_NOTICE);
            return;
        }
        if (is_a($value, 'nanomvc\Type'))
            // Transfer value automagically.
            $this->_cols[$name]->set($value->get());
        else
            // Just set value.
            $this->_cols[$name]->set($value);
    }

    /** Allows quickly creating model copies. */
    public function __clone() {
        // Copies must (naturally) be stored to be linked.
        $this->_id = -1;
        // Clone and relink all type handlers.
        foreach ($this->_cols as $column_name => &$type_instance) {
            $type_instance = clone $type_instance;
            $type_instance->parent = $this;
        }
    }

    /**
    * @desc Returns a list of the column names of the specified model.
    * @desc Note: Does not return the implicit ID column.
    * @return Array An array of the column names in the specified model.
    */
    public static final function getColumnNames($name) {
        static $columns_name_cache = array();
        if (isset($columns_name_cache[$name]))
            return $columns_name_cache[$name];
        $columns = array();
        if (!class_exists($name) || !is_subclass_of($name, 'nanomvc\Model'))
            trigger_error("'$name' is not a valid Model!", \E_USER_ERROR);
        foreach (get_class_vars($name) as $colname => $def)
            if ($colname[0] != '_')
                $columns[] = $colname;
        return $columns_name_cache[$name] = $columns;
    }

    /**
     * @desc Returns a list of the columns in this model for dynamic iteration.
     * @return Array An array of the columns in this model.
     */
    public final function getColumns() {
        return $_cols;
    }
    
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
     * Stores any changes to this model instance to the database.
     * If this is a new instance, it's inserted, otherwise, it's updated.
     */
    public function store() {
        $update_ex = null;
        if ($this->_id > 0) {
            // Updating existing row.
            $this->beforeStore(true);
            db\query($this->getUpdateSQL());
            $this->afterStore(true);
        } else {
            // Inserting.
            $this->beforeStore(false);
            db\query($this->getInsertSQL());
            $this->_id = db\insert_id();
            $this->afterStore(false);
        }
    }

    /** Unlinks this model instance from the database. */
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
        $table_name = string\cased_to_underline($name);
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
        $ret = db\query("DELETE FROM " . table($table_name) . " WHERE id = " . $id);
        unset(self::$_instance_cache[$name][$id]);
        $this->_id = -1;
        // After unlink event.
        $this->afterUnlink();
    }

    /**
     * Reverts all changes made to this model instance by flushing all
     * fields and reading them from database again.
     * Note: Unlinked instances cannot be reverted. Nothing happens on those.
     */
    public function revert() {
        if ($this->_id <= 0)
            return;
        $name = get_class($this);
        // Flush cache (or else we will get a copy of ourselves).
        unlink(self::$_instance_cache[$name][$this->_id]);
        // Select again  (read data again).
        $new_instance = $this->selectByID($this->_id);
        // Copy columns cache (data).
        $this->_columns_cache = $new_instance->_columns_cache;
        // Restore cache (so future selects will return this instance).
        self::$_instance_cache[$name][$this->_id] = $this;
    }


    /**
     * @desc Returns an array of pointers (table,column) that points to given table.
     */
    private function getPointerColumns($table_name) {
        static $cache = array();
        if (isset($cache[$table_name]))
            return $cache[$table_name];
        $pointers = array();
        $result = db\query("SELECT child_table,child_column FROM " . table("pointer_map") . " WHERE parent_table = \"$table_name\"");
        while (false !== ($row = db\next_array($result)))
            $pointers[] = array(string\underline_to_cased($row[0]), $row[1]);
        return $cache[$table_name] = $pointers;
    }

    private function getInsertSQL() {
        $name = get_class($this);
        $table_name = string\cased_to_underline($name);
        static $key_list_cache = array();
        if (!isset($key_list_cache[$table_name])) {
            $key_list = implode(',', self::getColumnNames($name));
            $key_list_cache[$table_name] = $key_list;
        } else
            $key_list = $key_list_cache[$table_name];
        $value_list = array();
        foreach ($this->getColumns() as $colname => $column) {
            $value_list[] = $column->getSQLValue();
            $column->setSyncPoint();
        }
        $value_list = implode(',', $value_list);
        return "INSERT INTO " . table($table_name) . " ($key_list) VALUES ($value_list)";
    }

    private function getUpdateSQL() {
        $table_name = string\cased_to_underline(get_class($this));
        $value_list = array();
        foreach ($this->getColumns() as $colname => $column) {
            $value_list[] = "`$colname`=" . $column->getSQLValue();
            $column->setSyncPoint();
        }
        $value_list = implode(',', $value_list);
        $id = intval($this->_id);
        return "UPDATE " . table($table_name) . " SET $value_list WHERE id=$id";
    }

    /**
    * @desc This function sets this model instance to the stored ID specified.
    * @return Model The model with the ID specified or FALSE.
    */
    public static function selectByID($id) {
        $id = intval($id);
        $name = get_called_class();
        $table_name = string\cased_to_underline($name);
        $model = self::touchModelInstance($table_name, $id);
        if ($model === false) {
            $id = intval($id);
            $model = new $name($id);
            $res = db\query("SELECT * FROM " . table($table_name) . " WHERE id = ".$id);
            if (db\get_num_rows($res) != 1)
                return false;
            $sql_row = db\next_assoc($res);
            $model = self::touchModelInstance($name, $id, $sql_row);
        }
        return $model;
    }

    /**
    * @desc Creates an array of model instances from a given sql result.
    */
    private static function makeArrayOf($name, $sql_result) {
        $length = db\get_num_rows($sql_result);
        if ($length == 0)
            return array();
        $array = array();
        for ($at = 0; $at < $length; $at++) {
            $sql_row = db\next_assoc($sql_result);
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
        foreach ($model->getColumns() as $colname => $column) {
            $column->setSQLValue($sql_row[strtolower($colname)]);
            $column->setSyncPoint();
        }
        return self::$_instance_cache[$name][$id] = $model;
    }

    /**
    * @desc Returns an array of all children of the specified child model. (Instances that point to this instance.)
    *       Will throw an exception if the specified child model does not point to this model.
    * @desc For security reasons, use db\strfy() to escape and quote
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
    * @desc For security reasons, use db\strfy() to escape and quote
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
        static $model_cache = array();
        if (!isset($model_cache[$child_model]))
            $model_cache[$child_model] = new $child_model(0);
        $child_model = $model_cache[$child_model];
        $ptr_fields = array();
        foreach ($child_model->getColumns() as $colname => $coltype)
            if ($coltype->parent == $name)
                $ptr_fields[] = $colname;
        if (count($ptr_fields) == 0)
            trigger_error("Invalid child model: '" . get_class($child_model) . "'. Does not contain pointer(s) to the model '$name'. (Expected field(s) '" . $table_name . "_id[_...]' missing)", \E_USER_ERROR);
        return $ptr_fields;
    }

    /**
    * @desc This function selects the first model instance that matches the specified $where clause.
    * @desc If there are no match, it returns FALSE.
    * @desc For security reasons, use db\strfy() to escape and quote
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
    * @desc For security reasons, use db\strfy() to escape and quote
    * @desc any strings you want to build your sql query from.
    * @param String $where (WHERE xyz) If specified, any number of where conditionals to filter out rows.
    * @param Integer $offset (OFFSET xyz) The offset from the begining to select results from.
    * @param Integer $limit (LIMIT offset,xyz) If you want to limit the number of results, specify this.
    * @param String $order (ORDER BY xyz) Specify this to get the results in a certain order, like 'description ASC'.
    * @desc Array An array of the selected model instances.
    */
    public static function selectWhere($where = "", $offset = 0, $limit = 0, $order = "") {
        $name = get_called_class();
        $table_name = string\cased_to_underline($name);
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
        $sql_result = db\query("SELECT * FROM " . table($table_name) . $where . $order . $limit);
        return self::makeArrayOf($name, $sql_result);
    }

    /**
    * @desc This function returns an array of model instances that matches the given SQL commands.
    * @desc For security reasons, use db\strfy() to escape and quote
    * @desc any strings you want to build your sql query from.
    * @param String $sqldata SQL command(s) that will be appended after the SELECT query for free selection.
    * @desc Array An array of the selected model instances.
    */
    public static function selectFreely($sqldata) {
        $name = get_called_class();
        $table_name = string\cased_to_underline($name);
        $sql_result = db\query("SELECT * FROM " . table($table_name) . " " . $sqldata);
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
    * @desc For security reasons, use db\strfy() to escape and quote
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
    * @desc For security reasons, use db\strfy() to escape and quote
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
    * @desc For security reasons, use db\strfy() to escape and quote
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
    * @desc For security reasons, use db\strfy() to escape and quote
    * @desc any strings you want to build your sql query from.
    * @param String $where (WHERE xyz) If specified, any number of where conditionals to filter out rows.
    * @return Integer Number of matched rows.
    */
    public static function count($where = "") {
        $table_name = string\cased_to_underline(get_called_class());
        if ($where != "")
            $where = " WHERE " . $where;
        $result = db\query("SELECT COUNT(*) FROM " . table($table_name) . $where);
        $rows = db\next_array($result);
        return intval($rows[0]);
    }


    /** Locks read and write on this model.
     * Useful when doing critical operations.
     * @see \nanomvc\db\unlock() for unlocking all locks.  */
    public static function lock($read = true, $write = true) {
        $table_name = string\cased_to_underline(get_called_class());
        // "If a table is to be locked with a read and a write lock,
        // put the write lock request before the read lock request."
        if ($write)
            db\run("LOCK TABLES " . table($table_name) . " WRITE");
        if ($read)
            db\run("LOCK TABLES " . table($table_name) . " READ");
    }

    public static final function syncronize_all_models() {
        // Refresh pointer map.
        db\run("DROP TABLE " . table("pointer_map"));
        db\query("CREATE TABLE " . table("pointer_map") . " (`child_table` varchar(64) NOT NULL, `child_column` varchar(64) NOT NULL, `parent_table` varchar(64) NOT NULL);");
        // Locate and sync all models.
        $model_paths = array(
            APP_DIR . "/models/",
            APP_DIR . "/modules/*/models/",
            APP_CORE_DIR . "/modules/*/models/",
        );
        foreach ($model_paths as $model_path) {
            $model_filenames = glob($model_path . "*_model.php");
            $has_module = $model_path != $model_paths[0];
            foreach ($model_filenames as $model_filename) {
                if (!is_file($model_filename))
                    continue;
                $table_name = substr(basename($model_filename), 0, -10);
                $cls_name = \nanomvc\string\underline_to_cased($table_name);
                if ($has_module) {
                    $module_name = basename(dirname(dirname($model_filename)));
                    $cls_name = $module_name . "\\" . $cls_name;
                    $table_name = $module_name . "\\" . $table_name;
                }
                $cls_name = "nanomvc\\" . $cls_name . "Model";
                // Expect model to be declared after require.
                if (!class_exists($cls_name))
                    trigger_error("Found model file that didn't declare it's expected model: $cls_name", \E_USER_ERROR);
                // Ignore models that are abstract.
                $model_reflection_class = new \ReflectionClass($cls_name);
                if ($model_reflection_class->isAbstract())
                    continue;
                // Syncronize this model.
                $model = new $cls_name(-1);
                db\sync_table_layout_with_model($table_name, $model);
                // Record pointers for pointer map.
                $child_table = strfy($table_name);
                $columns = self::getColumnNames($cls_name);
                foreach ($model->getColumns() as $col_name => $col_type) {
                    $child_column = strfy($col_name);
                    // Remove nanomvc/ (not part of table name - implicit)
                    $parent_table_name = substr(get_class($col_type->parent), 8);
                    // Table names are underlined.
                    $parent_table_name = string\cased_to_underline($parent_table_name);
                    $parent_table = strfy($parent_table_name);
                    db\query("INSERT INTO " . table("pointer_map") . " VALUES ($child_table, $child_column, $parent_table)");
                }
            }
        }
    }
}

/** A type defines what a model column stores, and how. */
abstract class Type {
    /** @var mixed The value of this type instance. */
    protected $value = null;
    /** @var Model The parent of this type instance. */
    public $parent = null;
    /** @var mixed The original value that was set from SQL. */
    protected $original_value = null;

    /** Returns the value from the last sync point. */
    public final function getSyncPoint() {
        return $this->original_value;
    }

    /**
     * Called to indicate that the type was synced so
     * that it can measure changes made from this point.
     */
    public final function setSyncPoint() {
        $this->original_value = $this->value;
    }

    /**
     * Returns TRUE if this type has changed since the last syncronization.
     */
    public final function hasChanged() {
        return $this->original_value != $this->value;
    }

    /** @desc Returns the value of this typed field. */
    public function get() {
        return $this->value;
    }
    /** Sets the value of this typed field. */
    public function set($value) {
        $this->value = $value;
    }

    /** Constructs this typed field with this initialized parent and value. */
    public function Type($value = null) {
        $this->value = $value;
    }

    /** Returns the data in a SQLized storeable form. */
    abstract public function getSQLValue();

    public function setSQLValue($value) {
        $this->value = $value;
    }

    /** Should return the SQL type that this input is stored in. */
    abstract public function getSQLType();

    /** Views this type. Should print representable HTML to output buffer. */
    abstract public function view();

    /**
    * @desc Should return an interface component that handles modification of the data in a form.
    * @param string $name The HTML name of the component.
    * @param string $label The label of the component.
    */
    abstract public function getInterface($name, $label);

    /**
     * Reads the component data from POST and possibly sets the value to something different.
     * If this function returns a string, that will be handled as a field error
     * that will be merged with whatever the model validate() returns.
    * @param string $name The HTML name of the component. */
    abstract public function readInterface($name);
}

/**
 * Reference handles pointers to other models.
 */
abstract class Reference extends Type {
    public $target_model;

    /**
     * If this is overridden and set to non null,
     * the type will not have it's target model parsed, but read from this
     * constant instead. (Eg. "some_module\SomeModel")
     */
    const STATIC_TARGET_MODEL = null;

    /** Returns the model target of this Reference. */
    public final function getTargetModel() {
        return $this->target_model;
    }

    /** Constructs this reference to the specified model. */
    public final function Reference($name, $id, $target_model) {
        parent::Type($name, $id);
        $this->target_model = $target_model;
    }

    /** Resolves this reference and returns the model it points to. */
    private function ref() {
        static $last_id = null, $last_resolve;
        $id = intval($this->value);
        if ($id === $last_id)
            return $last_resolve;
        else
            return $last_resolve = call_user_func(array($this->target_model, "selectByID"), $id);
    }

    // Using overloading to turn the ref function into a variable for convenience.
    public function __isset($name) {
        return $name == "ref";
    }

    public function __get($name) {
        if ($name == "ref")
            return $this->ref();
        else
            trigger_error("Attempted to read from a non existing variable '$name' on reference type.", \E_USER_ERROR);
    }

    public function __set($name, $value) {
        if ($name == "ref") {
            if (is_object($value)) {
                // Make sure this is a type of model we are pointing to.
                if (!is_a($value, $this->target_model))
                    trigger_error("Attempted to set a reference to an incorrect object. The reference expects " . $this->target_model . " objects, you gave it a " . get_class($value) . " object.", \E_USER_ERROR);
                $this->value = intval($value->getID());
            } else {
                // Assuming this is an ID.
                $this->value = intval($value);
            }
        } else
            trigger_error("Attempting to write to a non existing variable '$name' on reference type.", \E_USER_ERROR);
    }

    public function getSQLType() {
        return "int";
    }

    public function getSQLValue() {
        return intval($this->value);
    }
}

/**
 * A model that automatically unlinks itself whenever any of
 * the instances it's pointers points to gets unlinked.
 */
class GCModel extends Model {
    public function gcPointer($field_name) {
        $this->unlink();
    }
}
