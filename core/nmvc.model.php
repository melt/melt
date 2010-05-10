<?php namespace nmvc;

/**
 * nanoModel
 */
abstract class Model implements \Iterator {
    /** @var int Identifier of this data set.
     * Either NULL if unlinked or a random 128 bit hash. */
    protected $_id;
    /** @var array Where columns are internally stored for assignment overload. */
    private $_cols;
    /** @var array Cache of all columns in this model. */
    private $_columns_cache = null;
    /** @var array Cache of all fetched instances.  */
    private static $_instance_cache = array();

    
    /** Returns the ID of this model instance or NULL if unlinked. */
    public function getID() {
        return $this->_id > 0? intval($this->_id): null;
    }

    /** Returns true if this model instance is linked. */
    public function isLinked() {
        return $this->_id > 0;
    }

    /**
     * @desc Overidable event. Called on model instances before they are stored.
     * @param boolean $is_linked True if the model instance is currently linked in the database. False if it's about to be INSERTED.
     */
    public abstract function beforeStore($is_linked);

    /**
     * @desc Overidable event. Called on model instances after they are stored.
     * @param boolean $is_linked True if the model instance was linked in the database before the store. False if it was INSERTED just now.
     */
    public abstract function afterStore($was_linked);

    /**
     * @desc Overidable event. Called on model instances that is about to be unlinked in the database.
     */
    public abstract function beforeUnlink();

    /**
     * @desc Overidable event. Called on model instances after they have been unlinked in the database.
     */
    public abstract function afterUnlink();

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
    public abstract function gcPointer($field_name);

    /**
     * Override this function to implement application
     * level model access control.
     */
    public abstract function accessing();

    /** Override this function to initialize members of this model. */
    public abstract function initialize();

    /** Returns a parsed column array for a model. */
    private static function getParsedColumnArray($model_name) {
        static $parsed_model_cache = array();
        if (isset($parsed_model_cache[$model_name]))
            return $parsed_model_cache[$model_name];
        $parsed_col_array = array();
        $vars = get_class_vars($model_name);
        foreach ($vars as $column_name => $column_args) {
            // Ignore non column members.
            if ($column_name[0] == '_')
                continue;
            $column_construct_args = array();
            $column_attributes = array();
            
            if (!is_array($column_args)) {
                $type_class_name = $column_args;
            } else {
                // Read the first value, which should be it's type class.
                reset($column_args);
                $type_class_name = current($column_args);
                if (!is_string($type_class_name))
                    trigger_error("Invalid type: '$model_name.\$$column_name' does not specify a type class.", \E_USER_ERROR);
                unset($column_args[key($column_args)]);
                foreach ($column_args as $attr_key => $attr_value) {
                    if (is_integer($attr_key))
                        $column_construct_args[] = $attr_value;
                    else
                        $column_attributes[$attr_key] = $attr_value;
                }
            }
            $type_class_name = 'nmvc\\' . $type_class_name;
            if (!class_exists($type_class_name))
                trigger_error("Invalid model column: $model_name.\$$column_name - Type '$type_class_name' is undefined.", \E_USER_ERROR);
            if (!is_subclass_of($type_class_name, 'nmvc\Type'))
                trigger_error("Invalid model column: $model_name.\$$column_name - The specified type '$type_class_name' is not a nmvc\\Type.", \E_USER_ERROR);
            // Core pointer name convention check.
            $ends_with_id = string\ends_with($column_name, "_id");
            if (is_subclass_of($type_class_name, 'nmvc\core\PointerType')) {
                if (!$ends_with_id)
                    trigger_error("Invalid model column: $model_name.\$$column_name. nanoMVC name convention demands that all pointer type columns ends with '_id'!", \E_USER_ERROR);
            } else if ($ends_with_id)
                trigger_error("Invalid model column: $model_name.\$$column_name. nanoMVC name convention demands that only pointer type columns ends with '_id'!", \E_USER_ERROR);
            // Reflect the type constructor.
            $type_reflector = new \ReflectionClass($type_class_name);
            // The first argument is always the Type name.
            $column_construct_args = array_merge(array($column_name), $column_construct_args);
            // Check if correct number of args where passed to constructor.
            $constr_reflector = new \ReflectionMethod($type_class_name, "__construct");
            $tot_args = count($column_construct_args);
            $max_args = $constr_reflector->getNumberOfParameters();
            $min_args = $constr_reflector->getNumberOfRequiredParameters();
            if ($tot_args < $min_args || $tot_args > $max_args) {
                $tot_args--; $max_args--; $min_args--;
                trigger_error("Invalid model column: $model_name.\$$column_name - You supplied $tot_args arguments and the constructor of '$type_class_name' takes $min_args to $max_args arguments!", \E_USER_ERROR);
            }
            // Call the constructor.
            $type_handler = $type_reflector->newInstanceArgs($column_construct_args);
            foreach ($column_attributes as $key => $attribute) {
                if (!property_exists(get_class($type_handler), $key))
                    trigger_error("Invalid model column: $model_name.\$$column_name - The type '$type_class_name' does not have an attribute named '$key'.", \E_USER_ERROR);
                $type_handler->$key = $attribute;
            }
            // Cache this untouched type instance and clone it to other new instances.
            $parsed_col_array[$column_name] = $type_handler;
        }
        $parsed_model_cache[$model_name] = $parsed_col_array;
        return $parsed_col_array;
    }

    /**
    * @desc Translates the field specifiers to type handler instances.
    */
    protected final function __construct($id) {
        $this->accessing();
        // Copies all columns into this model.
        $this->_cols = self::getParsedColumnArray(get_class($this));
        foreach ($this->_cols as $column_name => &$type_instance) {
            // Assignment overload.
            unset($this->$column_name);
            // Cloning parsed type instance and link myself.
            $type_instance = clone $type_instance;
            $type_instance->parent = $this;
        }
        $this->_id = intval($id);
        $this->initialize();
        // Set sync point after initialization.
        foreach ($this->_cols as &$type_instance)
            $type_instance->setSyncPoint();
    }

    /** Helper function to get the actual type handler of a column. */
    public function type($name) {
        if (!isset($this->_cols[$name])) {
            trigger_error("Trying to read non existing column '$name' on model '" . get_class($this) . "'.", \E_ERROR);
            return;
        }
        return $this->_cols[$name];
    }

    /** Helper function to view a column. */
    public function view($name) {
        if (!isset($this->_cols[$name])) {
            trigger_error("Trying to read non existing column '$name' on model '" . get_class($this) . "'.", \E_ERROR);
            return;
        }
        return (string) $this->_cols[$name];
    }

    /** Assignment overloading. Returns value. */
    public function __get($name) {
        if (!isset($this->_cols[$name])) {
            trigger_error("Trying to read non existing column '$name' on model '" . get_class($this) . "'.", \E_USER_NOTICE);
            return;
        }
        return $this->_cols[$name]->get();
    }

    /** Assignment overloading. Sets value. */
    public function __set($name,  $value) {
        if (!isset($this->_cols[$name])) {
            trigger_error("Trying to write to non existing column '$name' on model '" . get_class($this) . "'.", \E_USER_NOTICE);
            return;
        }
        if (is_a($value, '\nmvc\Type'))
            // Transfer value automagically.
            $this->_cols[$name]->set($value->get());
        else
            // Just set value.
            $this->_cols[$name]->set($value);
    }

    /** Overloading isset due to assignment overloading. */
    public function __isset($name) {
        return isset($this->_cols[$name]);
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

    /** Describing the model as a string.
     * The default implementation is pretty poor. */
    public function __toString() {
        return get_class($this) . " #" . $this->getID();
    }

    /** Allows foreach iteration on models. */
    public function rewind() {
        reset($this->_cols);
    }

    public function current() {
        current($this->_cols);
    }

    public function key() {
        key($this->_cols);
    }

    public function next() {
        next($this->_cols);
    }

    public function valid() {
        current($this->_cols) !== false;
    }

    /**
    * @desc Returns a list of the column names.
    * @desc Note: Does not return the implicit ID column.
    * @return Array An array of the column names in the specified model.
    */
    public static final function getColumnNames() {
        $name = get_called_class();
        static $columns_name_cache = array();
        if (isset($columns_name_cache[$name]))
            return $columns_name_cache[$name];
        $columns = array();
        if (!class_exists($name) || !is_subclass_of($name, 'nmvc\Model'))
            trigger_error("'$name' is not a valid Model!", \E_USER_ERROR);
        foreach (get_class_vars($name) as $colname => $def)
            if ($colname[0] != '_')
                $columns[$colname] = $colname;
        return $columns_name_cache[$name] = $columns;
    }

    /**
     * @desc Returns a list of the columns in this model for dynamic iteration.
     * @return Array An array of the columns in this model.
     */
    public final function getColumns() {
        return $this->_cols;
    }
    
    /**
     * @desc Creates a new unlinked model instance.
     * @return Model A new unlinked model instance.
     */
    public static function insert() {
        $name = get_called_class();
        if (core\is_abstract($name))
            trigger_error("'$name' is an abstract class and therefore can't be inserted/created/instantized.", \E_USER_ERROR);
        $model = new $name(-1);
        return $model;
    }

    /**
     * Stores any changes to this model instance to the database.
     * If this is a new instance, it's inserted, otherwise, it's updated.
     */
    public function store() {
        if ($this->_id > 0) {
            // Updating existing row.
            $this->beforeStore(true);
            db\query($this->getUpdateSQL());
            $this->afterStore(true);
        } else {
            // Inserting.
            $this->beforeStore(false);
            db\query($this->getInsertSQL());
            if (db\config\USE_TRIGGER_SEQUENCING) {
                $id = db\next_array(db\query("SELECT @last_insert"));
                $id = $id[0];
            } else
                $id = db\insert_id();
            $this->_id = intval($id);
            $this->afterStore(false);
        }
    }

    /**
     * Returns TRUE if changed since last syncronization point.
     * @return boolean
     */
    public function hasChanged() {
        foreach ($this->_cols as &$type_instance)
            if ($type_instance->hasChanged())
                return true;
        return false;
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
        $id = intval($this->_id);
        // Find all pointers that will get broken by this unlink and garbage collect.
        $pointer_map = self::getMetaData("pointer_map");
        if (isset($pointer_map[$name]))
        foreach ($pointer_map[$name] as $pointer) {
            list($child_model, $child_column) = $pointer;
            $instances = $child_model::selectWhere("$child_column = $id");
            // Garbage collect.
            foreach ($instances as $instance) {
                // Don't call GC on unlinked instances. gcPointer() may unlink recursivly.
                if ($instance->_id > 0)
                    $instance->gcPointer($child_column);
            }
        }
        // Do the actual unlink in database backend.
        $table_name = self::classNameToTableName($name);
        db\run("DELETE FROM " . table($table_name) . " WHERE id = " . $id);
        unset(self::$_instance_cache[$id]);
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
        // Flush cache (or else we will get a copy of ourselves).
        unlink(self::$_instance_cache[$this->_id]);
        // Select again  (read data again).
        $new_instance = $this->selectByID($this->_id);
        // Copy columns cache (data).
        $this->_columns_cache = $new_instance->_columns_cache;
        // Restore cache (so future selects will return this instance).
        self::$_instance_cache[$this->_id] = $this;
    }

    /**
     * Returns the target model class name of a pointer in this model.
     */
    public final static function getTargetModel($pointer_name) {
        $model_name = get_called_class();
        $columns_array = self::getParsedColumnArray($model_name);
        if (!isset($columns_array[$pointer_name]))
            trigger_error("'$pointer_name' is not a column of '$model_name'.", \E_USER_ERROR);
        $column = $columns_array[$pointer_name];
        if (!is_subclass_of($column, 'nmvc\core\PointerType'))
            trigger_error("'$model_name.$pointer_name' is not a pointer column.", \E_USER_ERROR);
        return $column->getTargetModel();
    }

    private function getInsertSQL() {
        $name = get_class($this);
        $table_name = self::classNameToTableName($name);
        static $key_list_cache = array();
        if (!isset($key_list_cache[$table_name])) {
            $key_list = implode(',', $name::getColumnNames());
            $key_list_cache[$table_name] = $key_list;
        } else
            $key_list = $key_list_cache[$table_name];
        $value_list = array();
        foreach ($this->getColumns() as $colname => $column) {
            $value_list[] = $column->getSQLValue();
            $column->setSyncPoint();
        }
        $value_list = implode(',', $value_list);
        if (!db\config\USE_TRIGGER_SEQUENCING) {
            db\run("UPDATE " . table('core\seq') . " SET id = LAST_INSERT_ID(id+1)");
            $id = "LAST_INSERT_ID()";
        } else {
            $id = 0;
        }
        return "INSERT INTO " . table($table_name) . " (id, $key_list) VALUES ($id, $value_list)";
    }

    private function getUpdateSQL() {
        $table_name = self::classNameToTableName(get_class($this));
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
    * @return Model The model with the ID specified or NULL.
    */
    public static function selectByID($id) {
        $id = intval($id);
        if ($id <= 0)
            return null;
        $base_name = get_called_class();
        static $family_tree = null;
        if ($family_tree === null)
            $family_tree = self::getMetaData("family_tree");
        if (!isset($family_tree[$base_name]))
            trigger_error("Model '$base_name' is out of sync with database.", \E_USER_ERROR);
        foreach ($family_tree[$base_name] as $table_name) {
            $model_name = self::tableNameToClassName($table_name);
            $model = self::touchModelInstance($model_name, $id);
            if ($model !== null)
                return $model;
            $res = db\query("SELECT * FROM " . table($table_name) . " WHERE id = ".$id);
            if (db\get_num_rows($res) == 1) {
                $sql_row = db\next_assoc($res);
                return self::touchModelInstance($model_name, $id, $sql_row);
            }
        }
        return null;
    }

    /**
     * Creates an array of model instances from a given sql result.
     * @param array $out_array Array to append results too.
     */
    private static function makeArrayOf($model_class_name, $sql_result, &$out_array) {
        $length = db\get_num_rows($sql_result);
        if ($length == 0)
            return array();
        for ($at = 0; $at < $length; $at++) {
            $sql_row = db\next_assoc($sql_result);
            $id = $sql_row['id'];
            $out_array[$id] = self::touchModelInstance($model_class_name, $id, $sql_row);
        }
    }

    /**
    * @desc Passing all sql result model instancing through this function to enable caching.
    * @param Mixed $sql_row Either a sql row result or null if function should return null instead of instancing model.
    */
    private static function touchModelInstance($model_class_name, $id, $sql_row = null) {
        if (isset(self::$_instance_cache[$id]))
            return self::$_instance_cache[$id];
        if ($sql_row === null)
            return null;
        $model = new $model_class_name($id);
        foreach ($model->getColumns() as $colname => $column) {
            $column->setSQLValue($sql_row[strtolower($colname)]);
            $column->setSyncPoint();
        }
        return self::$_instance_cache[$id] = $model;
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

    /** Returns the name of the child pointer fields. */
    private function getChildPointers($child_model_name) {
        $model_name = get_class($this);
        $ptr_fields = array();
        foreach($child_model_name::getColumnNames() as $col_name) {
            if (substr($col_name, -3) == "_id" &&
            is($model_name, $child_model_name::getTargetModel($col_name)))
                $ptr_fields[] = $col_name;
        }
        if (count($ptr_fields) == 0)
            trigger_error("Invalid child model: '" . $child_model_name . "'. Does not contain pointer(s) to the model '$model_name'.", \E_USER_ERROR);
        return $ptr_fields;
    }

    /**
    * @desc This function selects the first model instance that matches the specified $where clause.
    * @desc If there are no match, it returns NULL.
    * @desc For security reasons, use db\strfy() to escape and quote
    * @desc any strings you want to build your sql query from.
    * @param String $where (WHERE xyz) If specified, any ammount of conditionals to filter out the row.
    * @param String $order (ORDER BY xyz) Specify this to get the results in a certain order, like 'description ASC'.
    * @desc Model The model instance that matches or NULL if there are no match.
    */
    public static function selectFirst($where, $order = "") {
        $match = self::selectWhere($where, 0, 1, $order);
        if (count($match) == 0)
            return null;
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
        $offset = intval($offset);
        $limit = intval($limit);
        if ($where != "")
            $where = " WHERE " . $where;
        if ($limit != 0)
            $limit = " LIMIT " . $offset . "," . $limit;
        else if ($offset != 0)
            $limit = " OFFSET " . $offset;
        else $limit = "";
        if ($order != "")
            $order = " ORDER BY " . $order;
        return self::selectFreely($where . $order . $limit);
    }

    /**
     * This function returns an array of model instances that matches the
     * given SQL commands.
     * For security reasons, use db\strfy() to escape and quote
     * any strings you want to build your sql query from.
     * @param string $sql_select_param SQL command(s) that will be appended
     * after the SELECT query for free selection.
     * @return array An array of the selected model instances.
    */
    public static function selectFreely($sql_select_param) {
        $name = get_called_class();
        $family_tree = self::getMetaData("family_tree");
        $out_array = array();
        if (!isset($family_tree[$name]))
            trigger_error("Model '$name' is out of sync with database.", \E_USER_ERROR);
        foreach ($family_tree[$name] as $table_name) {
            $sql_result = db\query("SELECT * FROM " . table($table_name) . " " . $sql_select_param);
            self::makeArrayOf(self::tableNameToClassName($table_name), $sql_result, $out_array);
        }
        return $out_array;
    }

    /**
    * @desc This function unlinks the model instance with the given ID.
    */
    public static function unlinkByID($id) {
        $instance = forward_static_call(array('nmvc\Model', "selectByID"), $id);
        if ($instance !== null)
            $instance->unlink();
    }
    /**
    * @desc This function unlinks the selection of instances that matches the given SQL commands.
    * @desc For security reasons, use db\strfy() to escape and quote
    * @desc any strings you want to build your SQL query from.
    * @param String $sqldata SQL command(s) that will be appended after the DELETE query for free selection.
    */
    public static function unlinkFreely($sqldata) {
        $instances = forward_static_call(array('nmvc\Model', "selectFreely"), $sqldata);
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
        $instances = forward_static_call(array('nmvc\Model', "selectWhere"), $where, $offset, $limit);
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
        if ($where != "")
            $where = " WHERE " . $where;
        $family_tree = self::getMetaData("family_tree");
        $count = 0;
        $name = get_called_class();
        if (!isset($family_tree[$name]))
            trigger_error("Model '$name' is out of sync with database.", \E_USER_ERROR);
        foreach ($family_tree[$name] as $table_name) {
            $result = db\query("SELECT COUNT(*) FROM " . table($table_name) . $where);
            $rows = db\next_array($result);
            $count += intval($rows[0]);
        }
        return $count;
    }


    /** Locks read and write on this model.
     * Useful when doing critical operations.
     * @see \nmvc\db\unlock() for unlocking all locks.  */
    public static function lock($read = true, $write = true) {
        $family_tree = self::getMetaData("family_tree");
        $name = get_called_class();
        if (!isset($family_tree[$name]))
            trigger_error("Model '$name' is out of sync with database.", \E_USER_ERROR);
        foreach ($family_tree[$name] as $table_name) {
            // "If a table is to be locked with a read and a write lock,
            // put the write lock request before the read lock request."
            if ($write)
                db\run("LOCK TABLES " . table($table_name) . " WRITE");
            if ($read)
                db\run("LOCK TABLES " . table($table_name) . " READ");
        }
    }

    private static function tableNameToClassName($table_name) {
        static $cache = array();
        if (!isset($cache[$table_name])) {
            $base_offs = strrpos($table_name, '\\');
            $base_offs++;
            $cls_name = 'nmvc\\' . substr($table_name, 0, $base_offs) . string\underline_to_cased(substr($table_name, $base_offs)) . "Model";
            $cache[$table_name] = $cls_name;
        }
        return $cache[$table_name];
    }

    private static function classNameToTableName($class_name) {
        static $cache = array();
        if (!isset($cache[$class_name])) {
            // Remove nmvc prefix and Model suffix.
            $table_name = string\cased_to_underline(substr($class_name, 5, -5));
            $cache[$class_name] = $table_name;
        }
        return $cache[$class_name];
    }


    /**
     * Validates the current data. If invalid, returns an array of all fields
     * name => reason mapped, otherwise, returns an empty array.
     * Designed to be overriden.
     * @return array All invalid fields, name => reason mapped.
     */
    public abstract function validate();

    private static $_metadata_cache = array();

    protected static function getMetaData($key) {
        if (isset(self::$_metadata_cache[$key]))
            return self::$_metadata_cache[$key];
        $result = db\query("SELECT v FROM " . table('core\metadata') . " WHERE k = " . strfy($key));
        $result = db\next_array($result);
        if ($result !== false)
            $result = unserialize($result[0]);
        self::$_metadata_cache[$key] = $result;
        return $result;
    }

    protected static function setMetaData($key, $value) {
        self::$_metadata_cache[$key] = $value;
        db\run("REPLACE INTO " . table('core\metadata') . " (k,v) VALUES (" . strfy($key) . "," . strfy(serialize($value)) . ")");
    }

    public static final function syncronize_all_models() {
        // Clear metadata.
        db\run("DROP TABLE " . table('core\metadata'));
        db\run("CREATE TABLE " . table('core\metadata') . " (`k` varchar(16) NOT NULL PRIMARY KEY, `v` BLOB NOT NULL)");
        $creating_sequence = !in_array(db\config\PREFIX . 'core\seq', db\get_all_tables());
        // Locate and sync all models.
        $model_paths = array(
            APP_DIR . "/models/",
            APP_DIR . "/modules/*/models/",
            APP_CORE_DIR . "/modules/*/models/",
        );
        // Array that keeps track of all incomming pointers to tables.
        $pointer_map = array();
        $model_classes = array();
        $sequence_max = 1;
        foreach ($model_paths as $model_path) {
            $model_filenames = glob($model_path . "*_model.php");
            $has_module = $model_path != $model_paths[0];
            foreach ($model_filenames as $model_filename) {
                if (!is_file($model_filename))
                    continue;
                $table_name = substr(basename($model_filename), 0, -10);
                $cls_name = \nmvc\string\underline_to_cased($table_name);
                if ($has_module) {
                    $module_name = basename(dirname(dirname($model_filename)));
                    $cls_name = $module_name . "\\" . $cls_name;
                    $table_name = $module_name . "\\" . $table_name;
                }
                $cls_name = "nmvc\\" . $cls_name . "Model";
                // Expect model to be declared after require.
                if (!class_exists($cls_name))
                    trigger_error("Found model file that didn't declare it's expected model: $cls_name", \E_USER_ERROR);
                // Ignore models that are abstract.
                if (core\is_abstract($cls_name))
                    continue;
                $model_classes[$cls_name] = $cls_name;
                // Syncronize this model.
                $parsed_col_array = Model::getParsedColumnArray($cls_name);
                db\sync_table_layout_with_model($table_name, $parsed_col_array);
                // Record pointers for pointer map.
                $columns = $cls_name::getColumnNames();
                foreach ($parsed_col_array as $col_name => $col_type) {
                    if (substr($col_name, -3) != "_id")
                        continue;
                    $target_model = $col_type->getTargetModel();
                    $pointer_map[$target_model][] = array($cls_name, $col_name);
                }
                // If creating sequence, record max.
                if ($creating_sequence) {
                    $max_result = db\next_array(db\query("SELECT MAX(id) FROM " . table($table_name)));
                    $max_result = intval($max_result[0]);
                    if ($sequence_max < $max_result)
                        $sequence_max = $max_result;
                }
            }
        }
        self::setMetaData("pointer_map", $pointer_map);
        // Track all ancestors of all models.
        $family_tree = array();
        foreach ($model_classes as $model_class) {
            // A self relation allows us to loop trough all models that IS
            // that model, and itself IS that model.
            $model_class_table_name = self::classNameToTableName($model_class);
            $family_tree[$model_class][] = $model_class_table_name;
            foreach (class_parents($model_class) as $model_parent) {
                if ($model_parent == 'nmvc\Model')
                    continue;
                $family_tree[$model_parent][] = $model_class_table_name;
            }
        }
        self::setMetaData("family_tree", $family_tree);
        if ($creating_sequence) {
            // Need to create the sequence.
            db\run("CREATE TABLE " . table('core\seq') . " (id INT PRIMARY KEY NOT NULL)");
            db\run("INSERT INTO " . table('core\seq') . " VALUES (" . (intval($sequence_max) + 1) . ")");
        }
    }
}