<?php namespace nmvc;

/**
 * The Model. One of the fundamental objects in NanoMVC.
 * @see http://docs.nanomvc.com/chapter/development_guide/introduction_to_models
 *
 * Model states:
 *
 * Linked - Present in the database.
 * Unlinked - Only present in memory.
 *
 * Transition states:
 *
 * Stable - No transition in progress.
 * Updating - In beforeStore callback, updating changes to the database,
 *            possibly transitioning from unlinked to linked.
 * Unlinking - In beforeUnlink callback transitioning from linked to unlinked.
 * Blocking - Store and unlinks are ignored.
 *
 * In beforeStore:
 * store() - No effect.
 * unlink() - Will (unlink the model/cancel the link) and enter blocking state.
 *
 * In beforeUnlink:
 * store() - No effect.
 * unlink() - No effect.
 *
 */
abstract class Model implements \Iterator {
    /**
     * Identifier of this data set or <= 0 if unlinked.
     * @var int
     * @internal
     */
    protected $_id = 0;
    /** @var array Where columns are internally stored for assignment overload. */
    private $_cols;
    /** @var array Cache of all columns in this model. */
    private $_columns_cache = null;
    /** @var array Cache of all fetched instances.  */
    private static $_instance_cache = array();

    const TRANSITION_STABLE = 0;
    const TRANSITION_BLOCKING = 3;
    const TRANSITION_STORING = 2;
    const TRANSITION_UNLINKING = 1;

    /** @var integer Current transition state of this model instance. */
    private $_transition = self::TRANSITION_STABLE;

    
    /**
     * Returns the ID of this model instance or NULL if unlinked.
     * @return integer
     */
    public final function getID() {
        return $this->_id > 0? intval($this->_id): 0;
    }

    /**
     * Returns true if this model instance is linked.
     * @return boolean
     */
    public final function isLinked() {
        return $this->_id > 0;
    }

    /**
     * Overidable event. Called on model instances after they have
     * been inserted and are ready to be populated with default data.
     * @return void
     */
    protected abstract function initialize();

    /**
     * Overidable event. Called on model instances before they are stored.
     * @param boolean $is_linked True if the model instance is currently
     * linked in the database. False if it's about to be INSERTED.
     * @return void
     */
    protected abstract function beforeStore($is_linked);

    /**
     * Overidable event. Called on model instances after they are stored.
     * @param boolean $is_linked True if the model instance was linked in the
     * database before the store. False if it was INSERTED just now.
     * @return void
     */
    protected abstract function afterStore($was_linked);

    /**
     * Overidable event. Called on model instances that is about to be
     * unlinked in the database.
     * @return void
     */
    protected abstract function beforeUnlink();

    /**
     * Overidable event. Called on model instances after they have been
     * unlinked in the database.
     * @return void
     */
    protected abstract function afterUnlink();

    /**
     * Overidable event. Called on model instances after they have been
     * loaded from the database.
     * @return void
     */
    protected abstract function afterLoad();

    /**
     * Callback function that can is useful when the application requires
     * database partitioning. It will only be called once per request
     * and model, after which the result is cached.
     * Any model instances that does not match the given where condition will
     * literally be completly invisible to nanoMVC. In effect, this means
     * that any pointer relation that crosses between two partitions
     * will be cut off and reported as "invalid".
     * For every UNIQUE where_condition this function returns, a new
     * persistant mySQL view will be created. The view will never be deleted.
     * If you are generating many different types of filters, this can quickly
     * flood the database.
     * @return mixed If this callback returns NULL, no partitioning will
     * take place. Otherwise it will be treated as a mySQL where_condition.
     */
    protected static function getDatabasePartitionFilter() {
        return null;
    }

    /**
     * Called when model has a pointer that is CALLBACK disconnect reaction
     * configured and it's targeted instance is unlinked from database.
     * (Custom handling.)
     * @return void
     */
    protected abstract function disconnectCallback($pointer_name);

    /**
     * Validates the current data. If invalid, returns an array of all fields
     * name => reason mapped, otherwise, returns an empty array.
     * Designed to be overriden.
     * @return array All invalid fields, name => reason mapped.
     */
    public abstract function validate();

    /**
     * Returns a parsed column array for this model.
     * @return array
     */
    private static function getParsedColumnArray() {
        $model_name = get_called_class();
        static $parsed_model_cache = array();
        if (isset($parsed_model_cache[$model_name]))
            return $parsed_model_cache[$model_name];
        $parsed_col_array = array();
        $vars = get_class_vars($model_name);
        foreach ($vars as $column_name => $column_args) {
            // Ignore non column members.
            if ($column_name[0] == '_')
                continue;
            // Ignore reset columns
            if (is_null($column_args))
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
            if (!class_exists($type_class_name) || core\is_abstract($type_class_name))
                trigger_error("Invalid model column: $model_name.\$$column_name - Type '$type_class_name' is undefined or abstract.", \E_USER_ERROR);
            if (!is_subclass_of($type_class_name, 'nmvc\Type'))
                trigger_error("Invalid model column: $model_name.\$$column_name - The specified type '$type_class_name' is not a nmvc\\Type.", \E_USER_ERROR);
            // Core pointer name convention check.
            $ends_with_id = string\ends_with($column_name, "_id");
            $is_pointer_type = is($type_class_name, 'nmvc\core\PointerType');
            if ($ends_with_id && !$is_pointer_type)
                trigger_error("Invalid model column: $model_name.\$$column_name. The field ends with '_id' which is a reserved suffix for pointer type fields.", \E_USER_ERROR);
            else if (!$ends_with_id && $is_pointer_type)
                trigger_error("Invalid model column: $model_name.\$$column_name. Pointer type fields must end with '_id'.", \E_USER_ERROR);
            // Reflect the type constructor.
            $type_reflector = new \ReflectionClass($type_class_name);
            // The first argument is always the Type name.
            array_unshift($column_construct_args, $column_name);
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
     * Creates a new unlinked model instance.
     * @deprecated
     * @return Model A new unlinked model instance.
     */
    public static function insert() {
        $name = get_called_class();
        if (core\is_abstract($name))
            trigger_error("'$name' is an abstract class and therefore can't be inserted/created/instantized.", \E_USER_ERROR);
        return new $name();
    }

    /** @var boolean Set to true when loading and not inserting. */
    private static $_skip_initialize = false;

    /**
     * Creates a new instance of this model.
     */
    public final function __construct() {
        // Copies all columns into this model.
        $this->_cols = static::getParsedColumnArray();
        foreach ($this->_cols as $column_name => &$type_instance) {
            // Assignment overload.
            unset($this->$column_name);
            // Cloning parsed type instance and link myself.
            $type_instance = clone $type_instance;
            $type_instance->parent = $this;
        }
        // Enter default values.
        if (!self::$_skip_initialize)
            $this->initialize();
    }


    private function doSubResolve(&$name) {
        if (false !== $pos = strpos($name, "->")) {
            $subresolve = substr($name, $pos + 2);
            $name = substr($name, 0, $pos);
            return $subresolve;
        } else
            return null;
    }

    private function resolveGetClosure($name, $probing = false) {
        static $closures = array();
        $class_name = get_class($this);
        if (isset($closures[$class_name][$name]))
            return $closures[$class_name][$name];
        // ->id closure returns id of instance.
        $unresolved_name = $name;
        if ($name == "id") {
            $closure = function($columns, $id) {
                return $id > 0? intval($id): 0;
            };
        } else {
            $subresolve = self::doSubResolve($name);
            $id_companion = $name . "_id";
            if (isset($this->_cols[$id_companion])) {
                // ->xyz for existing xyz_id column returns object.
                $closure = function($columns) use ($id_companion, $subresolve) {
                    $ret = $columns[$id_companion]->get();
                    if ($subresolve !== null)
                        $ret = $ret->$subresolve;
                    return $ret;
                };
            } else if (!isset($this->_cols[$name])) {
                if ($probing)
                    return null;
                $closure = "Trying to read non existing field '$name'.";
            } else if ($subresolve !== null) {
                if ($probing)
                    return null;
                $closure = "Trying to use arrow reference operator on non-reference field '$name'!";
            } else if (substr($name, -3) == "_id") {
                // ->xyz_id closure returns id of pointer without resolving it.
                $closure = function($columns) use ($name) {
                    return $columns[$name]->getID();
                };
            } else {
                $closure = function($columns) use ($name) {
                    return $columns[$name]->get();
                };
            }
        }
        $closures[$class_name][$unresolved_name] = $closure;
        return $closure;
    }

    private function resolveSetClosure($name) {
        static $closures = array();
        $class_name = get_class($this);
        if (isset($closures[$class_name][$name]))
            return $closures[$class_name][$name];
        // ->id closure returns id of instance.
        $unresolved_name = $name;
        $subresolve = self::doSubResolve($name);
        $id_companion = $name . "_id";
        if (isset($this->_cols[$id_companion])) {
            // ->xyz for existing xyz_id field sets value by object reference
            $closure = function($columns, $value) use ($id_companion, $subresolve) {
                $type = $columns[$id_companion];
                if ($subresolve !== null)
                    $type->get()->$subresolve = $value;
                else if (is_object($value) || is_null($value))
                    $type->set($value);
                else
                    trigger_error("Setting pointer by unexpected type " . gettype($value) . " (expected null or object) Ignoring.", \E_USER_NOTICE);
            };
        } else if (!isset($this->_cols[$name])) {
            $closure = "Trying to access non existing field '$name'.";
        } else if ($subresolve !== null) {
            $closure = "Trying to use arrow reference operator on non-reference field '$name'!";
        } else if (substr($name, -3) == "_id") {
            // ->xyz_id can only set id. cast value to integer.
            $closure = function($columns, $value) use ($name) {
                if (is_object($value))
                    trigger_error("Setting pointer by unexpected type " . gettype($value) . " (expected non object integer id) Ignoring.", \E_USER_NOTICE);
                else
                    $columns[$name]->set($value);
            };
        } else {
            $closure = function($columns, $value) use ($name) {
                $columns[$name]->set($value);
            };
        }
        $closures[$class_name][$unresolved_name] = $closure;
        return $closure;
    }

    private function resolveTypeClosure($name) {
        static $closures = array();
        $class_name = get_class($this);
        if (isset($closures[$class_name][$name]))
            return $closures[$class_name][$name];
        // ->id closure returns id of instance.
        $unresolved_name = $name;
        $subresolve = self::doSubResolve($name);
        $id_companion = $name . "_id";
        if (isset($this->_cols[$id_companion])) {
            // ->xyz for existing xyz_id field resolves subtype.
            $closure = function($columns) use ($id_companion, $subresolve) {
                $ret = $columns[$id_companion];
                if ($subresolve !== null)
                    $ret = $ret->get()->type($subresolve);
                return $ret;
            };
        } else if (!isset($this->_cols[$name])) {
            $closure = "Trying to access non existing field '$name'.";
        } else if ($subresolve !== null) {
            $closure = "Trying to use arrow reference operator on non-reference field '$name'!";
        } else {
            $closure = function($columns) use ($name) {
                return $columns[$name];
            };
        }
        $closures[$class_name][$unresolved_name] = $closure;
        return $closure;
    }

    /** Assignment overloading. Returns value. */
    public function __get($name) {
        $get_closure = $this->resolveGetClosure($name);
        if (is_string($get_closure))
            trigger_error($get_closure, \E_USER_NOTICE);
        else
            return $get_closure($this->_cols, $this->_id);
    }

    /**
     * Returns true if this model has a field with the given name.
     * @return boolean
     */
    public function hasField($name) {
        return $this->resolveGetClosure($name, true) !== null;
    }

    /** Assignment overloading. Sets value. */
    public function  __set($name,  $value) {
        $set_closure = $this->resolveSetClosure($name);
        if (is_string($set_closure)) {
            trigger_error($set_closure, \E_USER_NOTICE);
            return;
        }
        $set_closure($this->_cols, $value);
    }

    /** Helper function to get the actual type handler of a column. */
    public function type($name) {
        $type_closure = $this->resolveTypeClosure($name);
        if (is_string($type_closure)) {
            trigger_error($type_closure, \E_USER_NOTICE);
            return;
        }
        return $type_closure($this->_cols);
    }

    /** Helper function to view a column. */
    public function view($name) {
        if ($name == "id")
            return (string) $this->getID();
        $type_closure = $this->resolveTypeClosure($name);
        if (is_string($type_closure)) {
            trigger_error($type_closure, \E_USER_NOTICE);
            return;
        }
        if (substr($name, -3) !== "_id")
            return (string) $type_closure($this->_cols);
        else
            return (string) $type_closure($this->_cols)->getID();
    }

    /** Overloading isset due to assignment overloading. */
    public function __isset($name) {
        $get_closure = $this->resolveGetClosure($name);
        if (!is_object($get_closure))
            return false;
        else
            return $get_closure($this->_cols, $this->_id) !== null;
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
     * Displays this model.
     * Contains an example implementation that should be overriden.
     */
    public function __toString() {
        if (!$this->isLinked())
            return __("Not Set");
        else
            return get_class($this) . " #" . $this->id;
    }

    /** Allows foreach iteration on models. */
    public function rewind() {
        return reset($this->_cols);
    }

    public function current() {
        return current($this->_cols);
    }

    public function key() {
        return key($this->_cols);
    }

    public function next() {
        return next($this->_cols);
    }

    public function valid() {
        return current($this->_cols) !== false;
    }

    /**
     * Returns a list of the column names.
     * Note: Does not return the implicit ID column.
     * @return array An array of the column names in the specified model.
     */
    public static final function getColumnNames() {
        $name = get_called_class();
        static $columns_name_cache = array();
        if (isset($columns_name_cache[$name]))
            return $columns_name_cache[$name];
        $columns = array();
        foreach (get_class_vars($name) as $colname => $default) {
            if ($default === null || $colname[0] == '_')
                continue;
            $columns[$colname] = $colname;
        }
        return $columns_name_cache[$name] = $columns;
    }

    /**
     * Returns a list of the columns in this model for dynamic iteration.
     * @return array An array of the columns in this model.
     */
    public final function getColumns() {
        return $this->_cols;
    }

    public function link() {

    }
    
    /**
     * Stores any changes to this model instance to the database.
     * If this is a new instance, it's inserted, otherwise, it's updated.
     */
    public function store() {
        // Storing is only possible in stable state..
        if ($this->_transition != self::TRANSITION_STABLE)
                return;
        // Enter "storing" transition state.
        $this->_transition = self::TRANSITION_STORING;
        // Determine if linking or syncronizing.
        $was_linked = $this->_id > 0;
        $this->beforeStore($was_linked);
        // If beforeStore resulted in blocking, cancel any further store attempt.
        if ($this->_transition == self::TRANSITION_BLOCKING) {
            $this->_transition = self::TRANSITION_STABLE;
            return;
        }
        if ($was_linked) {
            // Updating existing row.
            $query = $this->getUpdateSQL();
            // No query if nothing changed.
            if ($query !== null)
                db\query($query);
        } else {
            // Inserting (linking) new row.
            db\query($this->getInsertSQL());
            if (db\config\USE_TRIGGER_SEQUENCING) {
                $id = db\next_array(db\query("SELECT @last_insert"));
                $id = $id[0];
            } else
                $id = db\insert_id();
            $id = intval($id);
            $this->_id = $id;
            // Put this instance in the instance cache.
            self::$_instance_cache[$id] = $this;
            // Turn virtual object pointers to this instance
            // into real database pointers.
            foreach (core\PointerType::getIncommingMemoryObjectPointers($this) as $incomming_pointer) {
                list($instance, $ptr_field) = $incomming_pointer;
                if (!$instance->isLinked())
                    continue;
                $table_name = self::classNameToTableName(get_class($instance));
                db\query("UPDATE " . table($table_name) . " SET `$ptr_field` = $id WHERE id = " . $instance->_id);
                $instance->type($ptr_field)->setSyncPoint();
            }
        }
        // Exiting "storing" transition state and calling afterStore callback.
        $this->_transition = self::TRANSITION_STABLE;
        $this->afterStore($was_linked);
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
        // Can't unlink if unlinking/blocking.
        if ($this->_transition == self::TRANSITION_UNLINKING || $this->_transition == self::TRANSITION_BLOCKING)
            return;
        // If currently storing, cancel that operation and block further.
        $will_block = ($this->_transition == self::TRANSITION_STORING);
        // If already unlinked, there's nothing more to be done.
        if ($this->_id < 1) {
            $this->_transition = $will_block? self::TRANSITION_BLOCKING :self::TRANSITION_STABLE;
            return;
        }
        // Enter "unlinking" transition state.
        $this->_transition = self::TRANSITION_UNLINKING;
        // Prevent a user abort from terminating the script during GC.
        ignore_user_abort(true);
        // Extend the script time limit.
        set_time_limit(180);
        // Before unlink event.
        $this->beforeUnlink();
        // Gather information.
        $name = get_class($this);
        $old_id = intval($this->_id);
        $this->_id = -1;
        // Unlink from database.
        $table_name = self::classNameToTableName($name);
        db\query("DELETE FROM " . table($table_name) . " WHERE id = " . $old_id);
        // Remove from instance cache.
        unset(self::$_instance_cache[$old_id]);
        // Remove pointers that gets broken by this unlink
        //  and handle this disconnect according to it's configuration.
        // Nullifying pointers before doing *anything* else to prevent
        // a corrupt database state.
        $cascade_callbacks = array();
        $disconnect_callbacks = array();
        $pointer_map = self::getMetaData("pointer_map");
        if (isset($pointer_map[$name]))
        foreach ($pointer_map[$name] as $pointer) {
            list($child_model, $child_column) = $pointer;
            $instances = $child_model::selectWhere("$child_column = $old_id");
            if (count($instances) == 0)
                continue;
            $table_name = self::classNameToTableName($child_model);
            db\query("UPDATE " . table($table_name) . " SET `$child_column` = 0 WHERE $child_column = $old_id");
            foreach ($instances as $instance) {
                // Reflect the broken pointer in memory too as a state where
                // pointers also gets broken in memory on unlink is more
                // interesting from a developers POV.
                $column = $instance->_cols[$child_column];
                $column->setSQLValue(0);
                $column->setSyncPoint();
                // Index reactions.
                $disconnect_reaction = $column->getDisconnectReaction();
                if ($disconnect_reaction == "CASCADE")
                    $cascade_callbacks[] = $instance;
                else if ($disconnect_reaction == "CALLBACK")
                    $disconnect_callbacks[] = $instance;
            }
        }
        // Now handling the broken pointers according to configuration.
        foreach ($cascade_callbacks as $instance)
            $instance->unlink();
        // Custom callbacks after cascade, this allows us to guarantee
        // that any models that should have been cascade deleted
        // by a previous unlink has been so at the time of invoke.
        foreach ($disconnect_callbacks as $instance)
            $instance->disconnectCallback();
        // Exiting "unlinking" transition state.
        $this->_transition = $will_block? self::TRANSITION_BLOCKING :self::TRANSITION_STABLE;
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
     * @return string
     */
    public static function getTargetModel($pointer_name) {
        $model_name = get_called_class();
        $columns_array = static::getParsedColumnArray();
        if (!isset($columns_array[$pointer_name]))
            trigger_error("'$pointer_name' is not a column of '$model_name'.", \E_USER_ERROR);
        $column = $columns_array[$pointer_name];
        if (!($column instanceof core\PointerType))
            trigger_error("'$model_name.$pointer_name' is not a pointer column.", \E_USER_ERROR);
        return $column->getTargetModel();
    }

    /**
     * Returns an associative array of column pointer field names and the model
     * class names they point to.
     * @param boolean $as_id_fields Set to true to return fields
     * with suffixed '_id'.
     * @return array
     */
    public static function getPointerColumns($as_id_fields = true) {
        $self = get_called_class();
        static $cache = array();
        if (isset($cache[$self][$as_id_fields]))
            return $cache[$self][$as_id_fields];
        $ret = array();
        foreach (static::getParsedColumnArray() as $col_name => $column) {
            if (!($column instanceof core\PointerType))
                continue;
            if (!$as_id_fields)
                $col_name = substr($col_name, 0, -3);
            $ret[$col_name] = $column->getTargetModel();
        }
        return $cache[$self][$as_id_fields] = $ret;
    }

    /**
     * Checks that if the field exists and at the same time
     * translates it to the SQL table column name.
     */
    public static function translateFieldToColumn($col_name, $error_on_missing = true) {
        if ($col_name == "id")
            return $col_name;
        $columns = static::getParsedColumnArray();
        if (array_key_exists($col_name, $columns))
            return $col_name;
        $id_companion = $col_name . "_id";
        if (array_key_exists($id_companion, $columns))
            return $id_companion;
        if ($error_on_missing)
            trigger_error("The field $col_name does not exist on " . get_called_class(), \E_USER_ERROR);
        return $col_name;
    }

    private function getInsertSQL() {
        $name = get_class($this);
        $table_name = self::classNameToTableName($name);
        static $key_list_cache = array();
        if (!isset($key_list_cache[$table_name])) {
            $key_list = implode(',', $this->getColumnNames());
            $key_list_cache[$table_name] = $key_list;
        } else
            $key_list = $key_list_cache[$table_name];
        $value_list = array();
        foreach ($this->getColumns() as $colname => $column) {
            $column->prepareSQLValue();
            $value = $column->getSQLValue();
            if ($value === null || $value === "")
                trigger_error(get_class($column) . "::getSQLValue() returned null or zero-length string! This is an invalid SQL value.", \E_USER_ERROR);
            $value_list[] = $value;
            $column->setSyncPoint();
        }
        $value_list = implode(',', $value_list);
        if (!db\config\USE_TRIGGER_SEQUENCING) {
            db\run("UPDATE " . table('core\seq') . " SET id = LAST_INSERT_ID(id + 1)");
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
            if (!$column->hasChanged())
                continue;
            $column->prepareSQLValue();
            $value = $column->getSQLValue();
            if ($value === null || $value === "")
                trigger_error(get_class($column) . "::getSQLValue() returned null or zero-length string! This is an invalid SQL value.", \E_USER_ERROR);
            $value_list[] = "`$colname`=$value";
            $column->setSyncPoint();
        }
        if (count($value_list) == 0)
            return null;
        $value_list = implode(',', $value_list);
        $id = intval($this->_id);
        return "UPDATE " . table($table_name) . " SET $value_list WHERE id = $id";
    }

    /**
     * Returns the model instance of the class called as
     * and linked with the specified id.
     * @return Model The model with the ID specified or NULL.
     */
    public static function selectByID($id) {
        $id = intval($id);
        if ($id <= 0)
            return null;
        $base_name = get_called_class();
        $model = @self::$_instance_cache[$id];
        if ($model !== null)
            return ($model instanceof $base_name)? $model: null;
        static $family_tree = null;
        if ($family_tree === null)
            $family_tree = self::getMetaData("family_tree");
        if (!isset($family_tree[$base_name]))
            trigger_error("Model '$base_name' is out of sync with database.", \E_USER_ERROR);
        foreach ($family_tree[$base_name] as $table_name) {
            $model_class_name = self::tableNameToClassName($table_name);
            $return_data = $model_class_name::findDataForSelf($model_class_name::getColumnNames(), "WHERE id = $id");
            if (count($return_data) > 0)
                return $model_class_name::instanceFromData($id, reset($return_data));
        }
        return null;
    }

    /**
     * Passing all sql result model instancing through this function to enable caching.
     * @return Model The cached model.
     */
    private static function instanceFromData($id, $data_row) {
        if (isset(self::$_instance_cache[$id]))
            return self::$_instance_cache[$id];
        $model_class_name = get_called_class();
        self::$_skip_initialize = true;
        $instance = new $model_class_name();
        self::$_skip_initialize = false;
        $instance->_id = $id;
        $value = reset($data_row);
        foreach ($instance->getColumns() as $column) {
            $column->setSQLValue($value);
            $column->setSyncPoint();
            $value = next($data_row);
        }
        $instance->afterLoad();
        return self::$_instance_cache[$id] = $instance;
    }

    /**
    * @desc Returns an array of all children of the specified child model. (Instances that point to this instance.)
    *       Will throw an exception if the specified child model does not point to this model.
    * @desc For security reasons, use db\strfy() to escape and quote
    *       any strings you want to build your sql query from.
    * @param String $child_model Name of the child model that points to this model.
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
     * Returns the number of children of the specified child model.
     * (Instances that point to this instance.)
     * Will throw an exception if the specified child model does not point
     * to this model. For security reasons, use db\strfy() to escape and quote
     * any strings you want to build your sql query from.
     * @param string $chold_model Name of the child model that points
     * to this model.
     * @param string $where (WHERE xyz) If specified, any number of where
     * conditionals to filter out rows.
     * @return integer Count of child models.
     */
    public final function countChildren($child_model, $where = "") {
        $ptr_fields = $this->getChildPointers($child_model);
        $id = $this->getID();
        $where = trim($where);
        if (strlen($where) > 0)
            $where = " AND $where";
        $where = "(" . implode(" = $id OR ", $ptr_fields) . " = $id)" . $where;
        return $child_model::count($where);
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
     * This function selects the first model instance that matches the specified
     * $where clause. If there are no match, it returns NULL.
     * For security reasons, use db\strfy() to escape and quote
     * @desc any strings you want to build your sql query from.
     * @param string $where (WHERE xyz) If specified, any ammount of
     * conditionals to filter out the row.
     * @param string $order (ORDER BY xyz) Specify this to get the results in
     * a certain order, like 'description ASC'.
     * @return Model The model instance that matches or NULL if there
     * are no match.
     */
    public static function selectFirst($where, $order = "") {
        $match = self::selectWhere($where, 0, 1, $order);
        if (count($match) == 0)
            return null;
        reset($match);
        return current($match);
    }

    /**
     * This function returns an array of model instances that is selected by the given SQL arguments.
     * For security reasons, use db\strfy() to escape and quote
     * any strings you want to build your sql query from.
     * @param string $where (WHERE xyz) If specified, any number of where conditionals to filter out rows.
     * @param integer $offset (OFFSET xyz) The offset from the begining to select results from.
     * @param integer $limit (LIMIT offset,xyz) If you want to limit the number of results, specify this.
     * @param string $order (ORDER BY xyz) Specify this to get the results in a certain order, like 'description ASC'.
     * @return array An array of the selected model instances.
     */
    public static function selectWhere($where = "", $offset = 0, $limit = 0, $order = "") {
        $offset = intval($offset);
        $limit = intval($limit);
        if ($where != "")
            $where = " WHERE " . $where;
        if ($limit != 0)
            $limit = " LIMIT " . $offset . "," . $limit;
        else if ($offset != 0)
            $limit = " LIMIT " . $offset . ",18446744073709551615";
        else
            $limit = "";
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
            $model_class_name = self::tableNameToClassName($table_name);
            $result_array = $model_class_name::findDataForSelf($model_class_name::getColumnNames(), $sql_select_param);
            foreach ($result_array as $id => $result_row)
                $out_array[$id] = $model_class_name::instanceFromData($id, $result_row);
        }
        return $out_array;
    }

    /**
     * Finding data. Compiles the data in an index based array matrix instead
     * of instancing for performance. Use -> referencing in column selection
     * or match conditions.
     * @param string $where Filter conditions to match columns with.
     * @param integer $offset Offset of data to start selecting from.
     * @param integer $limit Limit of data rows to select.
     * @param string $order (ORDER BY xyz) Specify this to get the results in a certain order, like 'description ASC'.
     * @return array An matrix of returned data.
     */
    public static function findData($columns_to_select, $where = "", $offset = 0, $limit = 0, $order = "") {
        // Compile and transform filter query.
        if ($where != "")
            $where = " WHERE " . $where;
        if ($order != "")
            $order = " ORDER BY " . $order;
        $filter_query = $where . $order;
        // Compile limit query.
        $offset = intval($offset);
        $limit = intval($limit);
        if ($limit != 0)
            $limit_query = " LIMIT " . $offset . "," . $limit;
        else if ($offset != 0)
            $limit_query = " OFFSET " . $offset;
        else
            $limit_query = "";
        return static::findDataFreely($columns_to_select, $filter_query . " " . $limit_query);
    }

    /**
     * Finding data. Compiles the data in an index based array matrix instead
     * of instancing for performance. Use -> referencing in column selection
     * or match conditions.
     * @param array $columns_to_select Columns to return in result.
     * @param string $sql_select_params mySQL select parameters.
     * @return array An matrix of returned data.
     */
    public static function findDataFreely($columns_to_select, $sql_select_params) {
        // Select for all child tables.
        $name = get_called_class();
        $family_tree = self::getMetaData("family_tree");
        if (!isset($family_tree[$name]))
            trigger_error("Model '$name' is out of sync with database.", \E_USER_ERROR);
        $return_data = array();
        foreach ($family_tree[$name] as $table_name) {
            $model_class_name = self::tableNameToClassName($table_name);
            $result_array = $model_class_name::findDataForSelf($columns_to_select, $sql_select_params);
            $return_data = array_merge($return_data, $result_array);
        }
        return $return_data;
    }

    /**
     * Registers a semantic pointer and returns the data array for it.
     * @return array
     */
    private static function registerSemanticColumn($column_name, &$columns_data) {
        if (isset($columns_data[$column_name]))
            return $columns_data[$column_name];
        $base_model_alias = string\from_index(0);
        if (false === strpos($column_name, "->")) {
            $col_sql_ref = $base_model_alias . '.' . static::translateFieldToColumn($column_name);
            $columns_data[$column_name] = array($col_sql_ref, null, null, null);
            return $columns_data[$column_name];
        }
        $ref_columns = explode("->", $column_name);
        $parent_class = get_called_class();
        $parent_alias = $base_model_alias;
        $parent_pointer_column = $ref_columns[0];
        $parent_pointer = $cur_pointer = $ref_columns[0];
        // (col_sql_ref, target_model, table_join_alias, left_join).
        reset($ref_columns);
        while (false !== $ref_column = next($ref_columns)) {
            if (substr($parent_pointer_column, -3) == "_id")
                trigger_error("Syntax error in semantic query: Remove '_id' suffix when resolving pointer colum with the arrow operator.", \E_USER_ERROR);
            $parent_pointer_column .= "_id";
            if (!array_key_exists($parent_pointer_column, $parent_class::getPointerColumns()))
                trigger_error("Syntax error in semantic query: The pointer column '$parent_pointer_column' is not declared for '$parent_class'!", \E_USER_ERROR);
            if (!isset($columns_data[$parent_pointer][1])) {
                $cur_target_model = $columns_data[$parent_pointer][1] = $parent_class::getTargetModel($parent_pointer_column);
                $cur_alias = $columns_data[$parent_pointer][2] = string\from_index(count($columns_data));
                $target_table = self::classNameToTableName($cur_target_model);
                $columns_data[$parent_pointer][3] = "LEFT JOIN " . table($target_table) . " AS $cur_alias ON $cur_alias.id = $parent_alias.$parent_pointer_column";
            } else {
                $cur_target_model = $columns_data[$parent_pointer][1];
                $cur_alias = $columns_data[$parent_pointer][2];
            }
            $cur_pointer .= "->" . $ref_column;
            if (!isset($columns_data[$cur_pointer])) {
                $col_sql_ref = $cur_alias . '.' . $cur_target_model::translateFieldToColumn($ref_column);
                $columns_data[$cur_pointer] = array($col_sql_ref, null, null, null);
            }
            $parent_class = $cur_target_model;
            $parent_alias = $cur_alias;
            $parent_pointer_column = $ref_column;
            $parent_pointer = $ref_column;
        }
        return $columns_data[$column_name];
    }

    /**
     * Transforms any semantic columns in the query to raw SQL references.
     * @return string
     */
    private static function transformSemanticColumnQuery($query, &$columns_data) {
        // If query doesn't contain any dollars, there's no semantic column pointers.
        if (false === strpos($query, '$'))
            return $query;
        // Tokenize query, break out column pointers.
        $string_extracting_pattern = <<<EOP
#([^"']+)|("(\\.|[^"])*"|'(\\.|[^'])*')#
EOP;
        $column_extract_pattern = <<<EOP
#(\\$[a-zA-Z][a-zA-Z0-9_>-]*)|([^\\$]+)|\\$#
EOP;
        preg_match_all($string_extracting_pattern, $query, $matches, PREG_SET_ORDER);
        $translated_query = '';
        foreach ($matches as $match) {
            $other_blob = $match[1];
            if (strlen($other_blob) == 0) {
                // Just pass string blobs on.
                $string_blob = $match[2];
                $translated_query .= $string_blob;
                continue;
            }
            preg_match_all($column_extract_pattern, $other_blob, $tokens, PREG_SET_ORDER);
            foreach ($tokens as $token) {
                if (isset($token[2]) && strlen($token[2]) > 0) {
                    // Just pass non-semantic column reference tokens on.
                    $translated_query .= $token[2];
                    continue;
                }
                // Replace the semantic column reference with a sql reference.
                $col_token = $token[1];
                $column_name = substr($col_token, 1);
                $column_data = static::registerSemanticColumn($column_name, $columns_data);
                $translated_query .= $column_data[0];
            }
        }
        return $translated_query;
    }

    /**
     * Finding data just for the model it was called on. (Used internally.)
     * Compiles the data in an index based array matrix instead
     * of instancing for performance. Use -> referencing in column selection
     * or match conditions.
     * @param array $columns_to_select Columns to return in result.
     * @param string $sql_select_params mySQL select parameters.
     * @param bool $supress_id Set to true to supress the ID column and not return any ID in result.
     * @return array An matrix of returned data.
     */
    protected static function findDataForSelf($columns_to_select, $sql_select_params, $supress_id = false) {
        // SELECT a.per_module, a.peak, b.shortcode, c.name FROM `consumption` AS a LEFT JOIN `media` AS b ON b.id = a.media_id
        $alias_offset = 1;
        // Contains pointers mapped to (col_sql_ref, target_model, table_join_alias, left_join).
        $columns_data = array();
        // Register all semantic columns.
        foreach ($columns_to_select as &$column) {
            if ($column[0] != '$' && strpos($column, '(') === false) {
                $column_data = static::registerSemanticColumn($column, $columns_data);
                $column = $column_data[0];
            } else {
                // Do not check if column exists, can be a viritual mySQL
                // column like COUNT(*) for example.
                $column = static::translateFieldToColumn($column, false);
            }
        }
        $sql_select_columns = ($supress_id? "": string\from_index(0) . ".id,") . implode(",", $columns_to_select);
        $sql_select_columns = static::transformSemanticColumnQuery($sql_select_columns, $columns_data);
        // Transform any semantic column references to hard SQL ones.
        $sql_select_params = static::transformSemanticColumnQuery($sql_select_params, $columns_data);
        // Compile left joins.
        $left_joins = array();
        foreach ($columns_data as $column_data) {
            $left_join = $column_data[3];
            if ($left_join != null)
                $left_joins[] = $left_join;
        }
        $left_joins = implode(" ", $left_joins);
        // Evaluate the from_name which can be a table or a view that defines a partition.
        $class_name = get_called_class();
        static $from_names = array();
        if (!array_key_exists($class_name, $from_names)) {
            $partition_filter = $class_name::getDatabasePartitionFilter();
            $table_name = table(self::classNameToTableName($class_name));
            if ($partition_filter !== null) {
                $from_name = \nmvc\db\config\PREFIX . "nprt/" . substr($table_name, 1, -1) . "/" . substr(sha1($partition_filter, false), 0, 8);
                $result = db\next_array(db\query("SELECT count(*) FROM `INFORMATION_SCHEMA`.`VIEWS` WHERE `TABLE_SCHEMA` = " . strfy(\nmvc\db\config\NAME) . " AND `TABLE_NAME` = '$from_name';"));
                $from_name = "`$from_name`";
                $view_exists = $result[0] != 0;
                if (!$view_exists)
                    db\query("CREATE VIEW $from_name AS SELECT * FROM $table_name WHERE $partition_filter", "The where_condition returned from getDatabasePartitionFilter() is invalid or database does not support creating views.");
            } else
                $from_name = $table_name;
            $from_names[$class_name] = $from_name;
        } else
            $from_name = $from_names[$class_name];
        // Comple the rest of the query.
        $main_alias = string\from_index(0);
        $found_rows_inject = self::$_have_pending_row_count? "SQL_CALC_FOUND_ROWS": "";
        $query = "SELECT $found_rows_inject $sql_select_columns FROM $from_name AS $main_alias $left_joins $sql_select_params";
        $result = db\query($query);
        if (self::$_have_pending_row_count) {
            $found_rows_result = db\next_array(db\query("SELECT FOUND_ROWS()"));
            self::$_found_row_count += intval($found_rows_result[0]);
        }
        $return_data = array();
        if (!$supress_id) while (false !== ($row = db\next_array($result)))
            $return_data[$row[0]] = array_splice($row, 1);
        else while (false !== ($row = db\next_array($result)))
            $return_data[] = $row;
        return $return_data;
    }

    private static $_have_pending_row_count = false;
    private static $_found_row_count;

    /**
     * Will start counting how many results would have been returned
     * without limit statements. This can decreese the performance of
     * queries greatly so make sure you call stopCountNolimit() when
     * you are done counting.
     * @return void
     */
    public static function startCountNolimit() {
        self::$_have_pending_row_count = true;
        self::$_found_row_count = 0;
    }

    /**
     * Returns the number of results that would have been returned
     * without limit statements, and stops counting.
     * @return integer
     */
    public static function stopCountNolimit() {
        self::$_have_pending_row_count = false;
        return self::$_found_row_count;
    }

    /**
     * This function unlinks the model instance with the given ID.
     * @param integer $id The ID of the model instance to unlink.
     * @return bool TRUE if model instance was found and unlinked,
     * otherwise FALSE.
     */
    public static function unlinkByID($id) {
        $instance = forward_static_call(array('nmvc\Model', "selectByID"), $id);
        if ($instance !== null) {
            $instance->unlink();
            return true;
        } else
            return false;
    }
    /**
     * This function unlinks the selection of instances that matches the given SQL commands.
     * For security reasons, use db\strfy() to escape and quote
     * any strings you want to build your SQL query from.
     * @param String $sqldata SQL command(s) that will be appended after the DELETE query for free selection.
     * @return integer Number of model instances found and unlinked.
     */
    public static function unlinkFreely($sqldata) {
        $instances = forward_static_call(array('nmvc\Model', "selectFreely"), $sqldata);
        foreach ($instances as $instance)
            $instance->unlink();
        return count($instances);
    }

    /**
     * This function removes the selection of fields that matches the
     * given SQL arguments.
     * For security reasons, use db\strfy() to escape and quote
     * any strings you want to build your sql query from.
     * @param String $where (WHERE xyz) If specified, any number of where conditionals to filter out rows.
     * @param Integer $offset (OFFSET xyz) The offset from the begining to select results from.
     * @param Integer $limit (LIMIT offset,xyz) If you want to limit the number of results, specify this.
     * @return integer Number of model instances found and unlinked.
     */
    public static function unlinkWhere($where = "", $offset = 0, $limit = 0) {
        $instances = forward_static_call(array('nmvc\Model', "selectWhere"), $where, $offset, $limit);
        foreach ($instances as $instance)
            $instance->unlink();
        return count($instances);
    }

    /**
     * Removes all children of the specified child model.
     * Instances with a pointer which points to this instance.
     * Will throw an exception if the specified child model does not point
     * to this model.
     * For security reasons, use db\strfy() to escape and quote
     * any strings you want to build your sql query from.
     * @param String $chold_model Name of the child model that points to this model.
     * @param String $where (WHERE xyz) If specified, any number of where conditionals to filter out rows.
     * @param Integer $offset (OFFSET xyz) The offset from the begining to unlink results from.
     * @param Integer $limit (LIMIT offset,xyz) If you want to limit the number of results, specify this.
     * @return integer Number of model instances found and unlinked.
     */
    public final function unlinkChildren($child_model, $where = "", $offset = 0, $limit = 0) {
        $instances = $this->selectChildren($child_model, $where, $offset, $limit);
        foreach ($instances as $instance)
            $instance->unlink();
        return count($instances);
    }

    /**
    * @desc This function counts the number of model instances that matches given SQL arguments.
    * @desc For security reasons, use db\strfy() to escape and quote
    * @desc any strings you want to build your sql query from.
    * @param String $where (WHERE xyz) If specified, any number of where conditionals to filter out rows.
    * @return Integer Number of matched rows.
    */
    public static function count($where = "") {
        if (trim($where) != "")
            $where = " WHERE " . $where;
        $family_tree = self::getMetaData("family_tree");
        $count = 0;
        $name = get_called_class();
        if (!isset($family_tree[$name]))
            trigger_error("Model '$name' is out of sync with database.", \E_USER_ERROR);
        foreach ($family_tree[$name] as $table_name) {
            $rows = static::findDataForSelf(array("COUNT(*)"), $where, true);
            $count += intval($rows[0][0]);
        }
        return $count;
    }

    protected static function tableNameToClassName($table_name) {
        static $cache = array();
        if (!isset($cache[$table_name])) {
            $base_offs = strrpos($table_name, '\\');
            $base_offs++;
            $cls_name = 'nmvc\\' . substr($table_name, 0, $base_offs) . string\underline_to_cased(substr($table_name, $base_offs)) . "Model";
            $cache[$table_name] = $cls_name;
        }
        return $cache[$table_name];
    }

    protected static function classNameToTableName($class_name) {
        static $cache = array();
        if (!isset($cache[$class_name])) {
            // Remove nmvc prefix and Model suffix.
            $table_name = string\cased_to_underline(substr($class_name, 5, -5));
            $cache[$class_name] = $table_name;
        }
        return $cache[$class_name];
    }

    /**
     * Returns the backend table name for this model.
     * @return string
     */
    public static function getTableName() {
        if (core\is_abstract(\get_called_class()))
            return null;
        return self::classNameToTableName(\get_called_class());
    }

    private static $_metadata_cache = array();

    protected static function getMetaData($key) {
        if (isset(self::$_metadata_cache[$key]))
            return self::$_metadata_cache[$key];
        $result = db\query("SELECT v FROM " . table('core__metadata') . " WHERE k = " . strfy($key));
        $result = db\next_array($result);
        if ($result !== false)
            $result = unserialize($result[0]);
        self::$_metadata_cache[$key] = $result;
        return $result;
    }

    protected static function setMetaData($key, $value) {
        self::$_metadata_cache[$key] = $value;
        db\run("REPLACE INTO " . table('core__metadata') . " (k,v) VALUES (" . strfy($key) . "," . strfy(serialize($value)) . ")");
    }

    /**
     * Lists all model classes in application.
     */
    private static final function findAllModels() {
        $model_classes = array();
        // Locate and sync all models in all enabled modules.
        $model_paths = array(APP_DIR . "/models");
        foreach (\nmvc\internal\get_all_modules() as $module_params) {
            list($class, $path) = $module_params;
            $model_paths[] = $path . "/models";
        }
        // Array that keeps track of all incomming pointers to tables.
        $pointer_map = array();
        $model_classes = array();
        $sequence_max = 1;
        foreach ($model_paths as $model_path) {
            $model_filenames = glob($model_path . "/*_model.php");
            // In some conditions glob returns non arrays instead of empty array on no results. See glob() in manual.
            if (!is_array($model_filenames))
                continue;
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
                $model_classes[$table_name] = $cls_name;
            }
        }
        return $model_classes;
    }

    /**
     * This function purifies all models. It will delete any column
     * it finds which is not present in the current model layout.
     */
    public static final function purifyAllModels() {
        // This maintenance script can run forever.
        ignore_user_abort(false);
        $model_classes = self::findAllModels();
        echo "\nSearching for redundant/not used columns and removing them...\n\n";
        \ob_flush();
        \sleep(6);
        ignore_user_abort(true);
        set_time_limit(0);
        $found_count = 0;
        foreach ($model_classes as $table_name => $model_class) {
            $column_names = $model_class::getColumnNames();
            $description = db\query("DESCRIBE " . table($table_name));
            while (false !== ($column = db\next_assoc($description))) {
                $field_name = $column["Field"];
                if ($field_name == "id")
                    continue;
                // Purify dirty column.
                if (!\array_key_exists($field_name, $column_names)) {
                    db\query("ALTER TABLE " . table($table_name) . " DROP COLUMN `" . $field_name . "`");
                    $found_count++;
                }
            }
        }
        echo "\n\n\nDone. Removed $found_count columns.";
    }

    /**
     * This function repairs any broken pointer structures it finds
     * in the database by simulating that the already deleted instances
     * becomes deleted.
     * @return void
     */
    public static final function repairAllModels() {
        // This maintenance script can run forever.
        define("NANOMVC_REPAIRING_IN_PROGRESS", 1);
        ignore_user_abort(true);
        set_time_limit(0);
        $cascade_callbacks = array();
        $disconnect_callbacks = array();
        $model_classes = self::findAllModels();
        $family_tree = self::getMetaData("family_tree");
        // Find intersecting ID's.
        echo "\nSearching for corrupt (intersected) primary keys...\n\n";
        foreach ($model_classes as $table_name_a => $model_class_a) {
            $in_table = array();
            foreach ($model_classes as $table_name_b => $model_class_b) {
                if ($table_name_b == $table_name_a)
                    continue;
                $in_table[] = "(id IN (SELECT id FROM " . table($table_name_b) . "))";
            }
            $rows = db\query("SELECT id FROM " . table($table_name_a) . " WHERE " . implode(" OR ", $in_table));
            $row_count = db\get_num_rows($rows);
            if ($row_count == 0)
                continue;
            echo "\nFound " . $row_count . " instances of " . $model_class_a . " with their PRIMARY KEY corrupt! Repairing...\n\n";
            while (false !== ($row = db\next_array($rows))) {
                $id = intval($row[0]);
                // Insert the row again to gain new id and delete the old corrupt copy.
                db\query("INSERT IGNORE INTO " . table($table_name_a) . " SELECT * FROM " . table($table_name_a) . " WHERE id = " . $id);
                db\query("DELETE FROM " . table($table_name_a) . " WHERE id = " . $id);
            }
        }
        echo "\nSearching for corrupt pointers...\n\n";
        foreach ($model_classes as $table_name => $model_class) {
            $model_columns = $model_class::getParsedColumnArray();
            $pointer_fields = $model_class::getPointerColumns();
            if (count($pointer_fields) == 0)
                continue;
            foreach ($pointer_fields as $ptr_name => $target_model) {
                $disconnect_reaction = $model_columns[$ptr_name]->getDisconnectReaction();
                $query = array();
                if (!isset($family_tree[$target_model]))
                    trigger_error("Model '$target_model' is out of sync with database.", \E_USER_ERROR);
                foreach ($family_tree[$target_model] as $table_name)
                    $query[] = "($ptr_name NOT IN (SELECT id FROM " . table($table_name) . "))";
                if (count($query) > 0) {
                    $query = implode(" OR ", $query);
                    $instances = $model_class::selectFreely("WHERE $ptr_name > 0 AND ($query)");
                    if (count($instances) > 0) {
                        // Found pointers that are broken.
                        echo "\nFound " . count($instances) . " instances of " . $model_class . " with their " . $ptr_name . " pointer broken! Repairing...\n\n";
                        // Remove pointers from database (nullify).
                        $table_name = self::classNameToTableName($model_class);
                        db\query("UPDATE " . table($table_name) . " SET `$ptr_name` = 0 WHERE id IN (" . implode(",", array_keys($instances)) . ")");
                        // Reflect the broken pointers in memory.
                        foreach ($instances as $instance) {
                            $column = $instance->_cols[$ptr_name];
                            $column->setSQLValue(0);
                            $column->setSyncPoint();
                        }
                        // Index reactions.
                        if ($disconnect_reaction == "CASCADE")
                            $cascade_callbacks = array_merge($cascade_callbacks, $instances);
                        else if ($disconnect_reaction == "CALLBACK")
                            $disconnect_callbacks = array_merge($cascade_callbacks, $instances);
                    }
                }
                // The cascade reaction also implies that the ID is not NULL.
                // Unlink all models with NULL cascade marked pointers.
                if ($disconnect_reaction == "CASCADE") {
                    $instances = $model_class::selectWhere("$ptr_name <= 0");
                    if (count($instances) == 0)
                        continue;
                    echo "\nFound " . count($instances) . " instances of " . $model_class . " with their CASCADE pointer " . $ptr_name . " unset! Marking them for cascade unlinking...\n\n";
                    $cascade_callbacks = array_merge($cascade_callbacks, $instances);
                }
            }
            // Since this routine can be memory intensive, collecting cycles here.
            gc_collect_cycles();
        }
        echo "\nDone selecting. CASCADE unlinking " . count($cascade_callbacks) . " (initially) and calling " . count($disconnect_callbacks) . " disconnect callbacks...\n\n";
        // Handling the broken pointers according to configuration.
        foreach ($cascade_callbacks as $instance)
            $instance->unlink();
        gc_collect_cycles();
        foreach ($disconnect_callbacks as $instance)
            $instance->disconnectCallback();
    }

    private static function validateCoreSeq() {
        // Validate that seq has correct structure.
        $core_seq = db\query("DESCRIBE " . table('core__seq'));
        $column_desc = db\next_assoc($core_seq);
        if ($column_desc === null)
            return false;
        $expected = array(
            "field" => "id",
            "type" => "bigint",
            "null" => "no",
            "key" => "pri",
            "default" => NULL,
        );
        // Lowercase keys & values and remove non intersecting keys.
        $column_desc = array_change_key_case($column_desc, \CASE_LOWER);
        $column_desc = \array_intersect_key($column_desc, $expected);
        $column_desc = array_map(function($value) { return is_string($value)? strtolower($value): $value; }, $column_desc);
        // Remove any paranthesis from type.
        $column_desc["type"] = \preg_replace('#\(.*#', "", @$column_desc["type"]);
        if (!core\compare_arrays($column_desc, $expected))
            return false;
        // Cannot have additional columns.
        $column_desc = db\next_assoc($core_seq);
        return $column_desc === false;
    }

    public static final function syncronizeAllModels() {
        // This maintenance script can run forever.
        ignore_user_abort(true);
        set_time_limit(0);
        // Clear nanomvc partitioning views as they need to be recreated when adding fields.
        $result = db\query("SELECT TABLE_NAME FROM `INFORMATION_SCHEMA`.`VIEWS` WHERE `TABLE_SCHEMA` = " . strfy(\nmvc\db\config\NAME) . " AND `TABLE_NAME` LIKE '" . db\config\PREFIX . "nprt/%';");
        while (false !== ($row = db\next_array($result)))
            db\query("DROP VIEW " . table($row[0]));
        // Clear metadata.
        db\run("DROP TABLE " . table('core__metadata'));
        db\run("CREATE TABLE " . table('core__metadata') . " (`k` varchar(16) NOT NULL PRIMARY KEY, `v` BLOB NOT NULL)");
        $creating_sequence = !in_array(db\config\PREFIX . 'core__seq', db\get_all_tables());
        if (!$creating_sequence) {
            // Validate that seq still has one row.
            $result = db\query("SELECT count(*) FROM " . table('core__seq'));
            $row = db\next_array($result);
            $creating_sequence = ($row[0][0] != 1);
            if (!$creating_sequence)
                $creating_sequence = !self::validateCoreSeq();
        }
        $sequence_max = 1;
        $model_classes = self::findAllModels();
        foreach ($model_classes as $table_name => $model_class) {
            // Syncronize this model.
            $parsed_col_array = $model_class::getParsedColumnArray();
            db\sync_table_layout_with_model($table_name, $parsed_col_array);
            // Record pointers for pointer map.
            $columns = $model_class::getColumnNames();
            foreach ($parsed_col_array as $col_name => $col_type) {
                if (substr($col_name, -3) != "_id")
                    continue;
                $target_model = $col_type->getTargetModel();
                $pointer_map[$target_model][] = array($model_class, $col_name);
            }
            // If creating sequence, record max.
            if ($creating_sequence) {
                $max_result = db\next_array(db\query("SELECT MAX(id) FROM " . table($table_name)));
                $max_result = intval($max_result[0]);
                if ($sequence_max < $max_result)
                    $sequence_max = $max_result;
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
            db\run("DROP TABLE " . table('core__seq'));
            db\query("CREATE TABLE " . table('core__seq') . " (id BIGINT PRIMARY KEY NOT NULL)");
            db\query("INSERT INTO " . table('core__seq') . " VALUES (" . (intval($sequence_max) + 1) . ")");
        }
    }
}