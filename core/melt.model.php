<?php namespace melt;

/**
 * The Model. One of the fundamental objects in Melt Framework.
 * @see http://docs.meltframework.com/chapter/development_guide/introduction_to_models
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
abstract class Model implements \IteratorAggregate, \Countable {
    /**
     * Identifier of this data set or <= 0 if unlinked.
     * @var int
     */
    private $_id = 0;
    /**
     * Reserved id for storing this data set.
     * Zero if no id has been reserved.
     * @var int
     */
    private $_reserved_id = 0;
    /** @var array Where columns are internally stored for assignment overload. */
    private $_cols;
    /** @var array Cache of all fetched instances.  */
    private static $_instance_cache = array();
    /** @var boolean Volatile model instances will ignore store and unlink
     * requests so their changes cannot be saved, nor can they
     * be linked/unlinked. */
    private $_is_volatile = false;
    
    const TRANSITION_STABLE = 0;
    const TRANSITION_BLOCKING = 3;
    const TRANSITION_STORING = 2;
    const TRANSITION_UNLINKING = 1;

    /** @var integer Current transition state of this model instance. */
    private $_transition = self::TRANSITION_STABLE;

    /** @var array Array of index groups for this model. */
    protected $index_groups = array();
    
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
     * Returns true if this model instance is legacy.
     * @return boolean.
     */
    public final function isLegacy() {
        return !\property_exists($this, "_id");
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
     * It is responsible for returning a where condition that will limit any
     * selection to the model further, in effect, determining what partition
     * of the database to select from.
     * Be carful not to store any pointers that crosses partition boundaries
     * as those pointers will then be broken.
     * @return db\WhereCondition NULL or a db\WhereCondition to
     * partition database.
     */
    protected static function getPartitionCondition() {
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
     * Returns a parsed column array for this model.
     * @return array
     */
    private static function getParsedColumnArray() {
        $model_name = get_called_class();
        static $parsed_model_cache = array();
        if (isset($parsed_model_cache[$model_name]))
            return $parsed_model_cache[$model_name];
        $parsed_col_array = array();
        $vars = \get_class_vars($model_name);
        foreach ($vars as $column_name => $column_args) {
            // Ignore non column members.
            if ($column_name[0] == '_' || $column_name == "index_groups")
                continue;
            // Ignore reset columns
            if (is_null($column_args))
                continue;
            $column_construct_args = array();
            $column_attributes = array();
            $storage_type = null;
            if (!is_array($column_args)) {
                $type_class_name = $column_args;
            } else {
                // Read the first value, which should be it's type class.
                reset($column_args);
                $type_class_name = current($column_args);
                switch ($type_class_name) {
                case VOLATILE:
                case INDEXED:
                case NON_INDEXED:
                case INDEXED_UNIQUE:
                    $storage_type = $type_class_name;
                    unset($column_args[key($column_args)]);
                    $type_class_name = current($column_args);
                    break;
                }
                if (!is_string($type_class_name))
                    \trigger_error("Invalid type: '$model_name.\$$column_name' does not specify a type class.", \E_USER_ERROR);
                unset($column_args[key($column_args)]);
                foreach ($column_args as $attr_key => $attr_value) {
                    if (is_integer($attr_key))
                        $column_construct_args[] = $attr_value;
                    else
                        $column_attributes[$attr_key] = $attr_value;
                }
            }
            $type_class_name = 'melt\\' . $type_class_name;
            if (!class_exists($type_class_name) || core\is_abstract($type_class_name))
                \trigger_error("Invalid model column: $model_name.\$$column_name - Type '$type_class_name' is undefined or abstract.", \E_USER_ERROR);
            if (!is_subclass_of($type_class_name, 'melt\Type'))
                \trigger_error("Invalid model column: $model_name.\$$column_name - The specified type '$type_class_name' is not a melt\\Type.", \E_USER_ERROR);
            // Core pointer name convention check.
            $ends_with_id = string\ends_with($column_name, "_id");
            $is_pointer_type = is($type_class_name, 'melt\core\PointerType');
            if ($ends_with_id && !$is_pointer_type)
                \trigger_error("Invalid model column: $model_name.\$$column_name. The field ends with '_id' which is a reserved suffix for pointer type fields.", \E_USER_ERROR);
            else if (!$ends_with_id && $is_pointer_type)
                \trigger_error("Invalid model column: $model_name.\$$column_name. Pointer type fields must end with '_id'.", \E_USER_ERROR);
            // Set default storage type if not set.
            if ($storage_type === null)
                $storage_type = $is_pointer_type? INDEXED: NON_INDEXED;
            // Reflect the type constructor.
            $type_reflector = new \ReflectionClass($type_class_name);
            // Check if correct number of args where passed to constructor.
            $constr_reflector = new \ReflectionMethod($type_class_name, "__construct");
            $tot_args = \count($column_construct_args);
            $max_args = $constr_reflector->getNumberOfParameters();
            $min_args = $constr_reflector->getNumberOfRequiredParameters();
            if ($tot_args < $min_args || $tot_args > $max_args) {
                \trigger_error("Invalid model column: $model_name.\$$column_name - You supplied $tot_args arguments and the constructor of '$type_class_name' takes $min_args to $max_args arguments!", \E_USER_ERROR);
            }
            // Call the constructor and prepare arguments.
            $type_handler = $type_reflector->newInstanceArgs($column_construct_args);
            $type_handler->key = $column_name;
            $type_handler->storage_type = $storage_type;
            foreach ($column_attributes as $key => $attribute)
                $type_handler->$key = $attribute;
            // Cache this untouched type instance and clone it to other new instances.
            $parsed_col_array[$column_name] = $type_handler;
        }
        $parsed_model_cache[$model_name] = $parsed_col_array;
        if (!core\is_abstract($model_name)) {
            // Create prototype model instance as a dummy parent for the prototype
            // type instances. See __construct for more information.
            $prototype_model_instance = new $model_name(true);
        }
        // Return the parsed column array.
        return $parsed_col_array;
    }

    /**
     * Returns all indexes declared by this model with their respective
     * generated key mapped to their index data.
     * @return array(key1 => array(array(column_name1, ...), is_unique), ... )
     */
    public static function getIndexes() {
        $model_name = get_called_class();
        static $index_cache = array();
        if (isset($index_cache[$model_name]))
            return $index_cache[$model_name];
        // Define the constant PRIMARY index.
        $indexes = array("PRIMARY" => array("columns" => array("id"), "is_unique" => true));
        // Get all single column indexes.
        $parsed_columns = static::getParsedColumnArray();
        foreach ($parsed_columns as $column_name => $column) {
            $storage_type = $column->storage_type;
            $key_set = array($column_name);
            if ($storage_type == INDEXED
            || $storage_type == INDEXED_UNIQUE) {
                $indexes[db\key_set_to_index_id($key_set)]
                = array("columns" => $key_set, "is_unique" => ($storage_type == INDEXED_UNIQUE));
            }
        }
        // Read all multi column indexes.
        $class_vars = \get_class_vars($model_name);
        $index_groups = $class_vars["index_groups"];
        if (!\is_array($index_groups))
            \trigger_error("Error in $model_name declaration index_groups declaration! \$index_groups must be array!", \E_USER_ERROR);
        $current_columns = array();
        $scan_index_group_fn = function($index_groups) use (&$current_columns, &$indexes, &$scan_index_group_fn, $parsed_columns, $model_name) {
            foreach ($index_groups as $column => $value) {
                if (!\array_key_exists($column, $parsed_columns))
                    \trigger_error("Error in $model_name index_groups declaration! Column sets contains unknown column '$column'.", \E_USER_ERROR);
                if (\array_key_exists($column, $current_columns))
                    \trigger_error("Error in $model_name index_groups declaration! Column declared twice in group.", \E_USER_ERROR);
                $current_columns[$column] = 1;
                if (\is_array($value)) {
                    $scan_index_group_fn($value);
                } else {
                    $storage_type = $value;
                    if (\count($current_columns) < 2)
                        \trigger_error("Error in $model_name index_groups declaration! Column sets must contain two or more columns.", \E_USER_ERROR);
                    if ($storage_type !== INDEXED && $storage_type !== INDEXED_UNIQUE)
                        \trigger_error("Error in $model_name index_groups declaration! One or more storage types are unknown.", \E_USER_ERROR);
                    $columns = \array_keys($current_columns);
                    $index_id = db\key_set_to_index_id($columns);
                    $indexes[$index_id] = array("columns" => $columns, "is_unique" => ($storage_type == INDEXED_UNIQUE));
                }
                unset($current_columns[$column]);
            }
        };
        $scan_index_group_fn($index_groups);
        $index_cache[$model_name] = $indexes;
        return $indexes;
    }

    /**
     * Will commit and start a new chanined transaction. This is a
     * DANGEROUS and TIME CONSUMING operation that should be avoided
     * and only used in very special cases. Normally, you should write your
     * application so that a work unit is equal to a request. See request
     * level transactionality in the manual for more information. However,
     * for cron jobs and other scripts with side effects it can be impossible
     * to define a unit of work as a request. In that case a new transaction
     * has to be restarted. Commiting a transaction is trivial in MySQL but
     * dangerous in a Melt Framework request as the object database layer of Melt Framework
     * considers PHP instances and database instances equal and guarantees
     * that the selected instances the request works with exists.
     * Guaranteeing this is however no longer possible after a commit
     * so all instances immediately become obsolete. In addition, it is not
     * possible to explicitly destruct PHP object instances. Melt Framework chooses
     * the middleground here by deleting all instance data hopefully releasing
     * as much memory as possible while making all selected instances
     * impossible to use. AS A DEVELOPER, YOU SHOULD MAKE SURE THAT YOUR
     * APPLICATION DOES NOT ACCESS ANY PREVIOUSLY SELECTED INSTANCE AFTER
     * CALLING THIS FUNCTION. THE RESULTING EFFECT FROM DOING THIS IS
     * UNDEFINED.
     * @return void
     */
    public static function chainedTransactionCommit() {
        if (!db\config\REQUEST_LEVEL_TRANSACTIONALITY)
            return;
        // Unset as much instance data as possible.
        foreach (self::$_instance_cache as $instance) {
            foreach (\get_object_vars($instance) as $var => $data)
                unset($instance->$var);
        }
        // Clear all core instance caches.
        self::$_instance_cache = array();
        core\PointerType::_clearIncommingMemoryObjectPointers();
        // Restart transaction.
        db\query("COMMIT AND CHAIN");
    }

    /**
     * Returns true if this model instance is volatile.
     * Volatile model instances will ignore store and unlink
     * requests so their changes cannot be saved, nor can they
     * be linked/unlinked.
     * @return boolean
     */
    public function isVolatile() {
        return $this->_is_volatile;
    }

    /** @var boolean Is set to true when loading and not inserting. */
    private static $_skip_initialize = false;

    /**
     * Creates a new instance of this model.
     * @param boolean $volatile Wheather model instance should be volatile
     * or not. All model instances that are only ment to carry data and
     * not be stored should be constructed volatile.
     */
    public final function __construct($volatile = false) {
        $this->_is_volatile = $volatile;
        // Copies all columns into this model.
        $this->_cols = static::getParsedColumnArray();
        foreach ($this->_cols as $column_name => &$type_instance) {
            // Assignment overload.
            unset($this->$column_name);
            // Clone prototype type instance and link myself unless
            // this is a prototype type instance in which case only link.
            if ($type_instance->parent !== null)
                $type_instance = clone $type_instance;
            $type_instance->parent = $this;
        }
        unset($type_instance);
        if (!self::$_skip_initialize) {
            // Enter default values and set sync point.
            $this->initialize();
            foreach ($this->_cols as $type_instance)
                $type_instance->setSyncPoint();
        }
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
                    \trigger_error("Setting pointer by unexpected type " . gettype($value) . " (expected null or object) Ignoring.", \E_USER_NOTICE);
            };
        } else if (!isset($this->_cols[$name])) {
            $closure = "Trying to access non existing field '$name'.";
        } else if ($subresolve !== null) {
            $closure = "Trying to use arrow reference operator on non-reference field '$name'!";
        } else if (substr($name, -3) == "_id") {
            // ->xyz_id can only set id. cast value to integer.
            $closure = function($columns, $value) use ($name) {
                if (is_object($value))
                    \trigger_error("Setting pointer by unexpected type " . gettype($value) . " (expected non object integer id) Ignoring.", \E_USER_NOTICE);
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
                if ($subresolve !== null) {
                    $ret = $ret->get();
                    if ($ret !== null)
                        $ret = $ret->type($subresolve);
                }
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
        if ($this->isLegacy())
            \trigger_error("Trying to access legacy model instance. Accessing instances saved and selected before chainedTransactionCommit is unsafe and not allowed!", \E_USER_ERROR);
        $get_closure = $this->resolveGetClosure($name);
        if (is_string($get_closure))
            \trigger_error($get_closure, \E_USER_NOTICE);
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
            \trigger_error($set_closure, \E_USER_NOTICE);
            return;
        }
        $set_closure($this->_cols, $value);
    }

    /** Helper function to get the actual type handler of a column. */
    public function type($name) {
        $type_closure = $this->resolveTypeClosure($name);
        if (is_string($type_closure)) {
            \trigger_error($type_closure, \E_USER_NOTICE);
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
            \trigger_error($type_closure, \E_USER_NOTICE);
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
        // Clone, relink and resync all type handlers.
        $original_columns = static::getParsedColumnArray();
        foreach ($this->_cols as $name => &$type_instance) {
            $type_instance = clone $type_instance;
            $type_instance->parent = $this;
            $type_instance->setSyncPointReference($original_columns[$name]->get());
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

    /**
     * Returns the field count of this model instance.
     * (Not including id field.)
     * @return integer
     */
    public function count() {
        return \count($this->_cols);
    }


    /**
     * Returns an iterator that iterates over
     * the fields in this model instance.
     * (Not including id field.)
     * @return \ArrayIterator
     */
    public function getIterator() {
        return new \ArrayIterator($this->_cols);
    }

    /**
     * Returns a list of the column names.
     * Note: Does not return the implicit ID column.
     * @param boolean $include_volatile Set to false to not include volatile
     * fields.
     * @return array An array of the column names in the specified model.
     */
    public static final function getColumnNames($include_volatile = true) {
        $name = get_called_class();
        static $columns_name_cache = array();
        if (isset($columns_name_cache[$include_volatile][$name]))
            return $columns_name_cache[$include_volatile][$name];
        $columns = array();
        foreach (static::getParsedColumnArray() as $name => $column) {
            if (!$include_volatile && $column->storage_type === VOLATILE)
                continue;
            $columns[$name] = $name;
        }
        $columns_name_cache[$include_volatile][$name] = $columns;
        return $columns;
    }

    /**
     * Returns a list of the columns in this model for dynamic iteration.
     * @return array An array of the columns in this model.
     */
    public final function getColumns() {
        return $this->_cols;
    }

    /**
     * Returns an ID reserved for storing this instance or the id it has
     * already been stored as. If no ID has been reserved yet for storing
     * (default), and ID will be reserved.
     * @return integer
     */
    public function getReservedID() {
        if ($this->isLinked())
            return $this->_id;
        if ($this->_reserved_id <= 0) {
            db\query("UPDATE " . db\table('core__seq') . " SET id = LAST_INSERT_ID(id + 1)");
            $result = db\next_array(db\query("SELECT LAST_INSERT_ID()"));
            $this->_reserved_id = \intval($result[0]);
        }
        return $this->_reserved_id;
    }

    /**
     * Stores any changes to this model instance to the database.
     * If this is a new instance, it's inserted, otherwise, it's updated.
     * @return void
     */
    public function store() {
        // Volatile models cannot be stored.
        if ($this->_is_volatile)
            return;
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
            $id = \intval($id);
            if ($this->_reserved_id > 0) {
                // Switch ID to use previously reserved.
                $reserved_id = \intval($this->_reserved_id);
                db\query("UPDATE " . self::classNameToTableName(get_class($this)) . " SET id = $reserved_id WHERE id = $id");
                $id = $reserved_id;
            }
            $this->_id = $id;
            // Put this instance in the instance cache.
            self::$_instance_cache[$id] = $this;
            // Turn virtual object pointers to this instance
            // into real database pointers.
            foreach (core\PointerType::getIncommingMemoryObjectPointers($this) as $incomming_pointer) {
                list($type, $ptr_field) = $incomming_pointer;
                $instance = $type->parent;
                if (!$instance->isLinked())
                    continue;
                $table_name = self::classNameToTableName(get_class($instance));
                db\query("UPDATE " . db\table($table_name) . " SET `$ptr_field` = $id WHERE id = " . $instance->_id);
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
        // Volatile models cannot be unlinked.
        if ($this->_is_volatile)
            return;
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
        $class_name = get_class($this);
        $old_id = intval($this->_id);
        $this->_id = -1;
        // Unlink from database.
        $table_name = self::classNameToTableName($class_name);
        db\query("DELETE FROM " . db\table($table_name) . " WHERE id = " . $old_id);
        // Remove from instance cache.
        unset(self::$_instance_cache[$old_id]);
        // Resycronize all fields to default original value
        // for correct has changed check after unlink.
        foreach (static::getParsedColumnArray() as $column_name => $column)
            $this->_cols[$column_name]->setSyncPointReference($column->get());
        // Remove pointers that gets broken by this unlink
        //  and handle this disconnect according to it's configuration.
        // Nullifying pointers before doing *anything* else to prevent
        // a corrupt database state.
        $cascade_callbacks = array();
        $disconnect_callbacks = array();
        $pointer_map = self::getMetaData("pointer_map");
        if (isset($pointer_map[$class_name]))
        foreach ($pointer_map[$class_name] as $pointer) {
            list($child_model, $child_column) = $pointer;
            $instances = $child_model::select()->where($child_column)->is($old_id)->forUpdate()->all();
            if (count($instances) == 0)
                continue;
            $table_name = self::classNameToTableName($child_model);
            db\query("UPDATE " . db\table($table_name) . " SET `$child_column` = 0 WHERE $child_column = $old_id");
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
                    $disconnect_callbacks[] = array($instance, $child_column);
            }
        }
        // Now handling the broken pointers according to configuration.
        foreach ($cascade_callbacks as $instance)
            $instance->unlink();
        // Custom callbacks after cascade, this allows us to guarantee
        // that any models that should have been cascade deleted
        // by a previous unlink has been so at the time of invoke.
        foreach ($disconnect_callbacks as $callback)
            $callback[0]->disconnectCallback($callback[1]);
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
        unset(self::$_instance_cache[$this->_id]);
        // Select again  (read data again).
        $new_instance = $this->selectByID($this->_id);
        if ($new_instance === null) {
            // Update the state of this instance to unlinked.
            $this->_id = 0;
        } else {
            // Restore data from non volatile fields.
            foreach ($this->getColumnNames(false) as $col_name)
                $this->_cols[$col_name]->set($new_instance->_cols[$col_name]->get());
            // Restore cache (so future selects will return this instance).
            self::$_instance_cache[$this->_id] = $this;
        }
    }

    /**
     * Returns the target model class name of a pointer in this model.
     * @return string
     */
    public static function getTargetModel($pointer_name) {
        $model_name = get_called_class();
        $columns_array = static::getParsedColumnArray();
        if (!isset($columns_array[$pointer_name]))
            \trigger_error("'$pointer_name' is not a column of '$model_name'.", \E_USER_ERROR);
        $column = $columns_array[$pointer_name];
        if (!($column instanceof core\PointerType))
            \trigger_error("'$model_name.$pointer_name' is not a pointer column.", \E_USER_ERROR);
        return $column->getTargetModel();
    }

    /**
     * Returns an associative array of column pointer field names and the model
     * class names they point to.
     * @param boolean $as_id_fields Set to true to return fields
     * with suffixed '_id'.
     * @param boolean $include_volatile Also incldue fields which are volatile.
     * @return array
     */
    public static function getPointerColumns($as_id_fields = true, $include_volatile = false) {
        $self = get_called_class();
        static $cache = array();
        if (isset($cache[$self][$as_id_fields]))
            return $cache[$self][$as_id_fields];
        $ret = array();
        foreach (static::getParsedColumnArray() as $col_name => $column) {
            if (!($column instanceof core\PointerType))
                continue;
            if (!$include_volatile && $column->storage_type === VOLATILE)
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
        $columns = static::getColumnNames(false);
        if (array_key_exists($col_name, $columns))
            return $col_name;
        $id_companion = $col_name . "_id";
        if (array_key_exists($id_companion, $columns))
            return $id_companion;
        if ($error_on_missing)
            \trigger_error("The field $col_name does not exist on " . get_called_class(), \E_USER_ERROR);
        return $col_name;
    }

    private function getInsertSQL() {
        $name = get_class($this);
        $table_name = self::classNameToTableName($name);
        static $key_list_cache = array();
        if (!isset($key_list_cache[$table_name])) {
            $key_list = implode(',', $this->getColumnNames(false));
            $key_list_cache[$table_name] = $key_list;
        } else
            $key_list = $key_list_cache[$table_name];
        $value_list = array();
        foreach ($this->getColumns() as $colname => $column) {
            if ($column->storage_type === VOLATILE)
                continue;
            $column->prepareSQLValue();
            $value = $column->getSQLValue();
            if ($value === null || $value === "")
                \trigger_error(get_class($column) . "::getSQLValue() returned null or zero-length string! This is an invalid SQL value.", \E_USER_ERROR);
            $value_list[] = $value;
            $column->setSyncPoint();
        }
        $value_list = implode(',', $value_list);
        if (!db\config\USE_TRIGGER_SEQUENCING) {
            db\query("UPDATE " . db\table('core__seq') . " SET id = LAST_INSERT_ID(id + 1)");
            $id = "LAST_INSERT_ID()";
        } else {
            $id = 0;
        }
        return "INSERT INTO " . db\table($table_name) . " (id, $key_list) VALUES ($id, $value_list)";
    }

    private function getUpdateSQL() {
        $table_name = self::classNameToTableName(get_class($this));
        $value_list = array();
        foreach ($this->getColumns() as $colname => $column) {
            if (!$column->hasChanged())
                continue;
            if ($column->storage_type === VOLATILE)
                continue;
            $column->prepareSQLValue();
            $value = $column->getSQLValue();
            if ($value === null || $value === "")
                \trigger_error(get_class($column) . "::getSQLValue() returned null or zero-length string! This is an invalid SQL value.", \E_USER_ERROR);
            $value_list[] = "`$colname`=$value";
            $column->setSyncPoint();
        }
        if (count($value_list) == 0)
            return null;
        $value_list = implode(',', $value_list);
        $id = intval($this->_id);
        return "UPDATE " . db\table($table_name) . " SET $value_list WHERE id = $id";
    }

    /**
     * Returns the model instance of the class called as
     * and linked with the specified id.
     * @param integer $id ID to select.
     * @param boolean $is_for_update Select to true to select for update.
     * This
     * @return Model The model with the ID specified or NULL.
     */
    public static function selectByID($id, $is_for_update = false) {
        $id = intval($id);
        if ($id <= 0)
            return null;
        if (\array_key_exists($id, self::$_instance_cache)) {
            $model = self::$_instance_cache[$id];
            $base_name = \get_called_class();
            return ($model instanceof $base_name)? $model: null;
        }
        $id = (string) $id;
        static $cached_queries = array();
        foreach (static::getChildModels() as $model_class_name) {
            if (!\array_key_exists($model_class_name, $cached_queries)) {
                $select = $model_class_name::select();
                $columns = $model_class_name::getColumnNames(false);
                $columns[] = "id";
                $select->setSelectFields($columns);
                $select = self::buildSelectQuery($select, false);
                $cached_query = $select . (\strpos($select, " WHERE ") === false? " WHERE id = ": " AND id = ");
                $cached_queries[$model_class_name] = $cached_query;
            } else
                $cached_query = $cached_queries[$model_class_name];
            $result = db\query($cached_query . $id . " " . ($is_for_update? "FOR UPDATE": "LOCK IN SHARE MODE"));
            $row = db\next_array($result);
            if ($row !== false)
                return $model_class_name::instanceFromData(intval(end($row)), $row);
        }
        return null;
    }

    /**
     * Returns/begins a selection query for this model.
     * @param mixed $fields Array of fields or single field to limit selection.
     * Note that this is ignored when instancing selection. It is only useful
     * when fetching data matrix directly or selecting in subqueries.
     * @return db\SelectQuery
     */
    public static function select($fields = null) {
        if ($fields !== null && !is_array($fields))
            $fields = array($fields);
        return new db\SelectQuery(\get_called_class(), $fields);
    }

    /**
     * Returns/begins a selection query for instances of this exact model,
     * ignoring any child model instances.
     * @param mixed $fields Array of fields or single field to limit selection.
     * Note that this is ignored when instancing selection. It is only useful
     * when fetching data matrix directly or selecting in subqueries.
     * @return db\SelectQuery
     */
    public static function selectSelf($fields = null) {
        $selection = static::select($fields);
        $selection->setIsIgnoringChildren(true);
        return $selection;
    }

    /**
     * Returns/begins a selection query for the children that has the
     * specified parent.
     * @param Model $parent
     * @param mixed $fields Array of fields or single field to limit selection.
     * Note that this is ignored when instancing selection. It is only useful
     * when fetching data matrix directly or selecting in subqueries.
     * @return db\SelectQuery
     */
    public static function selectChildren(Model $parent, $fields = null) {
        $ptr_fields = static::getParentPointers(\get_class($parent), true);
        $id = $parent->getID();
        if ($fields != null && \is_string($fields))
            $fields = array($fields);
        $select_query = new db\SelectQuery(\get_called_class(), $fields);
        foreach ($ptr_fields as $ptr_field)
            $select_query->or($ptr_field)->is($id);
        return $select_query;
    }

    /**
     * Selects all instances that matches this instances given fields.
     * Takes N arguments (field names) and returns selection.
     * Indexing the fields will make this selection much faster.
     * @param String $field1 First field of N fields.
     * @return db\SelectQuery
     */
    public function selectMatch($field1) {
        $selection = static::select();
        foreach (\func_get_args() as $field)
            $selection->and($field)->is($this->$field);
        return $selection;
    }

    /**
     * Returns an array of model instances of this type that refer to this
     * instance and the name of the refering pointers. The returned instances
     * is not neccessarly linked. The memory relation graph state is
     * used, not the database one.
     * @param boolean $is_for_update Set to true if instances are intended
     * to be updated to reduce deadlock risk.
     * @return array Tuples of array(instance, pointer_field).
     */
    public static function getReferences(Model $instance, $is_for_update = true) {
        $out_array = array();
        $called_class = \get_called_class();
        // Populate array with local references to the instance.
        foreach (core\PointerType::getIncommingMemoryObjectPointers($instance) as $reference) {
            list($type, $ptr_field) = $reference;
            $instance = $type->parent;
            if (is(\get_class($instance), $called_class))
                $out_array[\spl_object_hash($instance) . "_" . $ptr_field] = $reference;
        }
        if (!$instance->isLinked())
            return $out_array;
        $instance_id = $instance->id;
        foreach (static::getChildModels() as $model_class_name) {
            $ptr_fields = $model_class_name::getParentPointers(\get_class($instance), false);
            if (\count($ptr_fields) == 0)
                continue;
            $select_query = new db\SelectQuery(\get_called_class(), $fields);
            foreach ($ptr_fields as $ptr_field)
                $select_query->or($ptr_field)->is($id);
            if ($is_for_update)
                $select_query->forUpdate();
            $instances = $select_query->all();
            foreach ($instances as $instance)
            foreach ($ptr_fields as $ptr_field) {
                if ($instance->{$ptr_field . "_id"} == $instance_id)
                    $out_array[\spl_object_hash($instance) . "_" . $ptr_field] = array($instance, $ptr_field);
            }
        }
        return $out_array;
    }

    /**
     * Morphs this instance into a new type of model, updating all in-memory
     * references to it. If this instance is linked, it will be unlinked and
     * the new instance stored in its place.
     * @param string $new_model_class Model to morph instance into.
     * @param boolean $copy_fields Set to false to not transfer field data
     * for fields with the same name.
     * @return Model The new morphed model instance.
     */
    public function morph($new_model_class, $copy_fields = true) {
        // Sanity checking.
        $called_class = \get_called_class();
        if (\strcasecmp($called_class, $new_model_class) === 0)
            return $this;
        if (!core\is($new_model_class, __CLASS__))
            \trigger_error("The new model class is not a model class.", \E_USER_ERROR);
        if (core\is_abstract($called_class))
            \trigger_error("The new model class is abstract.", \E_USER_ERROR);
        // Create the instance to morph into.
        $morphed_instance = new $new_model_class();
        // Copy shared data.
        if ($copy_fields) {
            $from_fields = $this->getColumns();
            $to_fields = $morphed_instance->getColumns();
            foreach (\array_intersect_key($from_fields, $to_fields) as $shared_field => $from_type) {
                $type = $morphed_instance->type($shared_field);
                if ($type->takes($from_type->get()))
                    $type->set($from_type->get());
            }
        }
        // Update the relation graph state.
        foreach (Model::getReferences($this, true) as $reference) {
            list($instance, $ptr_field) = $reference;
            $instance->$ptr_field = $morphed_instance;
        }
        // Flip stored state.
        if ($this->isLinked()) {
            $morphed_instance->store();
            $this->unlink();
        }
        return $morphed_instance;
    }

    /** Returns the name(s) of the child pointer fields. */
    private static function getParentPointers($parent_model_class, $expecting_pointers) {
        $child_model_class = \get_called_class();
        $ptr_fields = array();
        foreach ($child_model_class::getColumnNames(false) as $col_name) {
            if (substr($col_name, -3) == "_id" &&
            is($parent_model_class, $child_model_class::getTargetModel($col_name)))
                $ptr_fields[] = $col_name;
        }
        if ($expecting_pointers && count($ptr_fields) == 0)
            \trigger_error("Invalid child model: '" . $child_model_class . "'. Does not contain pointer(s) to '$parent_model_class'.", \E_USER_ERROR);
        return $ptr_fields;
    }

    /**
     * Passing all sql result model instancing through this function to enable caching.
     * @return Model The cached model.
     */
    private static function instanceFromData($id, $data_row) {
        if ($id <= 0)
            \trigger_error("Creating instance from data with invalid ID ($id). Possible database corruption.", \E_USER_ERROR);
        if (isset(self::$_instance_cache[$id]))
            return self::$_instance_cache[$id];
        $model_class_name = \get_called_class();
        self::$_skip_initialize = true;
        $instance = new $model_class_name();
        self::$_skip_initialize = false;
        $instance->_id = $id;
        $value = reset($data_row);
        foreach ($instance->getColumns() as $column) {
            if ($column->storage_type === VOLATILE)
                continue;
            $column->setSQLValue($value);
            $column->setSyncPoint();
            $value = next($data_row);
        }
        self::$_instance_cache[$id] = $instance;
        $instance->afterLoad();
        return $instance;
    }

    /**
     * This function returns an array of model instances from
     * the given select query.
     * @param string $select_query
     * @return array An array of the selected model instances.
    */
    public static function getInstancesForSelection(db\SelectQuery $select_query) {
        $from_model = $select_query->getFromModel();
        if ($from_model === null)
            \trigger_error("Selection query has no source/from model set.", \E_USER_ERROR);
        // Clone select query to prevent/isolate side effects.
        $select_query = clone $select_query;
        $out_array = array();
        $is_counting = $select_query->getIsCounting();
        $sum = 0;
        $child_models = $select_query->getIsIgnoringChildren()
        ? array(\get_called_class()): static::getChildModels();
        foreach ($child_models as $model_class_name) {
            $columns = $model_class_name::getColumnNames(false);
            $columns[] = "id";
            $select_query->setFromModel($model_class_name);
            $select_query->setSelectFields($columns);
            $result = $model_class_name::getDataForSelection($select_query, true);
            if ($is_counting) {
                $sum += $result;
                continue;
            }
            foreach ($result as $result_row) {
                $id = \intval(\end($result_row));
                $out_array[$id] = $model_class_name::instanceFromData($id, $result_row);
            }
        }
        return $is_counting? $sum: $out_array;
    }

    /**
     * Returns child models of this model.
     * @return array
     */
    public static function getChildModels() {
        $current_model = \get_called_class();
        if ($current_model == __CLASS__)
            $current_model = 'melt\AppModel';
        $family_tree = self::getMetaData("family_tree");
        if (!isset($family_tree[$current_model]))
            \trigger_error("Model '$current_model' is out of sync with database.", \E_USER_ERROR);
        return $family_tree[$current_model];
    }


    /**
     * Finds and returns snapshot data for the given select query.
     * By default no row locking is used but this can be enabled
     * by setting $locking_read to true.
     * @param string $select_query
     * @param boolean $locking_read Set this to true to enable row locking.
     * @return mixed
     */
    public static function getDataForSelection(db\SelectQuery $select_query, $locking_read = false) {
        if ($select_query->getFromModel() === null)
            \trigger_error("Selection query has no source/from model set.", \E_USER_ERROR);
        $query = self::buildSelectQuery($select_query, $locking_read);
        $result = db\query($query);
        if ($select_query->getIsCounting()) {
            $row = db\next_array($result);
            return \intval($row[0]);
        }
        $return_data = array();
        while (false !== ($row = db\next_array($result)))
            $return_data[] = $row;
        return $return_data;
    }


    /**
     * Builds a selection query in context of called model class.
     */
    private static function buildSelectQuery(db\SelectQuery $select_query, $locking_read = true, $columns_data = array(), &$alias_offset = 0, $base_model_alias = null, $query_stack = array()) {
        $from_model = $select_query->getFromModel();
        if ($from_model === null)
            \trigger_error("Given select query does not have an associated model.", \E_USER_ERROR);
        // Register base model alias.
        if ($base_model_alias === null) {
            $base_model_alias = string\from_index($alias_offset);
            $alias_offset++;
        }
        if ($select_query->getIsCounting()) {
            $columns_sql = "COUNT(*)";
        } else {
            $select_fields = $select_query->getSelectFields();
            if (!\is_array($select_fields) || \count($select_fields) == 0)
                \trigger_error("Selecting zero columns is not allowed. (Redundant)", \E_USER_ERROR);
            // Register/process all semantic columns.
            foreach ($select_fields as &$column) {
                $column_data = $from_model::registerSemanticColumn($column, $columns_data, $alias_offset, $base_model_alias, $query_stack);
                $column = $column_data[0];
            }
            $columns_sql = \implode(",", $select_fields);
        }
        // Get partition condition.
        static $partition_conditions = array();
        if (!\array_key_exists($from_model, $partition_conditions)) {
            $partition_condition = $from_model::getPartitionCondition();
            if ($partition_condition !== null && !($partition_condition instanceof db\WhereCondition))
                \trigger_error("$from_model::getPartitionCondition() returned incorrect value. Expected db\WhereCondition.", \E_USER_ERROR);
            $partition_conditions[$from_model] = $partition_condition;
        } else
            $partition_condition = $partition_conditions[$from_model];
        $additional_condition = $partition_condition;
        // Process groupwise orderings.
        $left_joins_sql = array();
        $groupwise_orderings = $select_query->getGroupwiseOrderings();
        if (\count($groupwise_orderings) > 0) {
            $left_join_on = array();
            $self_join_alias = string\from_index($alias_offset);
            $alias_offset++;
            foreach ($groupwise_orderings as $groupwise_ordering) {
                list($group_field, $order_field, $is_ascending) = $groupwise_ordering;
                list($group_column) = $from_model::getColumnPrototype($group_field);
                list($order_column) = $from_model::getColumnPrototype($order_field);
                $left_join_on[] = "$base_model_alias.$group_column = $self_join_alias.$group_column";
                $compare = $is_ascending? ">": "<";
                // The second OR clause makes sure only the highest ID are picked when all order_column in group are equal.
                $left_join_on[] = "(($base_model_alias.$order_column $compare $self_join_alias.$order_column) OR ($base_model_alias.$order_column = $self_join_alias.$order_column AND $base_model_alias.id < $self_join_alias.id))";
            }
            $left_joins_sql[] = "LEFT JOIN " . db\table(self::classNameToTableName($from_model))
            . " AS $self_join_alias ON " . \implode(" AND ", $left_join_on);
            $additional_condition = expr($additional_condition)->and(new db\InjectedCondition("$self_join_alias.id IS NULL"));
        }
        // Process select tokens.
        $select_tokens = $select_query->getTokens($additional_condition);
        $sql_select_expr = "";
        /*$nonindexed_notice_triggered = false;*/
        if (\count($select_tokens) > 0) {
            $current_model = null;
            $current_field_prototype_type = null;
            foreach ($select_tokens as $token) {
                if (\is_string($token)) {
                    $sql_select_expr .= " " . $token;
                } else if ($token instanceof db\IndexRequirement) {
                    $subresolve = $token->getSubresolve();
                    // No subresolve -> index model can only be selection from model
                    // Subresolve -> index model must be $current_model since
                    // at least one constant constraint has been used.
                    $index_model = $subresolve === null? $from_model: $current_model;
                    $token->verify($index_model);
                } else if ($token instanceof db\ModelField) {
                    $column_data = $from_model::registerSemanticColumn($token->getName(), $columns_data, $alias_offset, $base_model_alias, $query_stack);
                    $sql_select_expr .= " " . $column_data[0];
                    $current_field_prototype_type = $column_data[4];
                    $current_model = $column_data[1] !== null? $column_data[1]: $from_model;
                } else if ($token instanceof db\ModelFieldValue) {
                    $sqlize_value_fn = function($value) use ($current_field_prototype_type) {
                        if ($current_field_prototype_type !== null && !($current_field_prototype_type instanceof core\PointerType)) {
                            $current_field_prototype_type->set($value);
                            return " " . $current_field_prototype_type->getSQLValue();
                        } else {
                            return " " . intval(id($value));
                        }
                    };
                    $value = $token->getValue();
                    if (!\is_array($value)) {
                        // Single  value.
                        $sql_select_expr .= $sqlize_value_fn($value);
                    } else {
                        // Mysql array/set of values. (uncommon)
                        foreach ($value as &$value_value)
                            $value_value = $sqlize_value_fn($value_value);
                        $sql_select_expr .= "(" . \implode(",", $value) . ")";
                    }
                } else if ($token instanceof db\SelectQuery) {
                    // Inner selection.
                    $sql_select_expr .= " (";
                    $inner_columns_data = array();
                    \array_push($query_stack, $columns_data);
                    $sql_select_expr .= self::buildSelectQuery($token, false, $inner_columns_data, $alias_offset, null, $query_stack);
                    \array_pop($query_stack);
                    $sql_select_expr .= ")";
                }
            }
        } 
        // Compile left joins from column data.
        foreach ($columns_data as $column_datas) {
            $left_join = $column_datas[3];
            if ($left_join != null)
                $left_joins_sql[] = $left_join;
        }
        $left_joins_sql = \implode(" ", $left_joins_sql);
        $table_name = db\table(self::classNameToTableName($from_model));
        $found_rows_identifier = $select_query->getIsCalcFoundRows()? "SQL_CALC_FOUND_ROWS": "";
        $locking_read_mode = $locking_read? ($select_query->getIsForUpdate()? "FOR UPDATE": "LOCK IN SHARE MODE"): "";
        $query = "SELECT $found_rows_identifier $columns_sql FROM $table_name AS $base_model_alias $left_joins_sql $sql_select_expr $locking_read_mode";
        if ($select_query->getIsCounting() && $select_query->hasGroupBy()) {
            // When grouping mysql changes the count() function - negating this behaviour.
            $query = "SELECT COUNT(*) FROM ($query) AS " . string\from_index($alias_offset);
        }
        return $query;
    }

    /**
     * Registers a semantic pointer in context of called model class and
     * returns the data for it. Used when building selection queries.
     * Automatically generates left join for column if required.
     * @param string $column_name Semantic column name (without starting $)
     * @param array $cur_columns_data Contains pointers mapped to (col_sql_ref, target_model, table_join_alias, left_join, prototype_type).
     * @param integer $alias_offset Current alias offset (for table aliases to prevent column name collision).
     * @return array Data array for column.
     */
    public static function registerSemanticColumn($column_name, &$columns_data, &$alias_offset, $base_model_alias, &$query_stack) {
        $refcleaned_column_name = \preg_replace('#^(<-)*#', '', $column_name);
        $escape_subquery_refcount = (\strlen($column_name) - \strlen($refcleaned_column_name)) / 2;
        if ($escape_subquery_refcount > 0) {
            $column_name = $refcleaned_column_name;
            $offset = \count($query_stack) - $escape_subquery_refcount;
            if ($offset < 0)
                \trigger_error("Syntax error in semantic query: '$column_name' has $escape_subquery_refcount escape subquery operators, but the current subquery is only " . \count($query_stack) . " level(s) deep!", \E_USER_ERROR);
            $cur_columns_data =& $query_stack[$offset];
        } else
            $cur_columns_data =& $columns_data;
        if (isset($cur_columns_data[$column_name]))
            return $cur_columns_data[$column_name];
        if (false === \strpos($column_name, "->")) {
            list($column_name, $prototype_type) = static::getColumnPrototype($column_name);
            $col_sql_ref = $base_model_alias . '.' . $column_name;
            $cur_columns_data[$column_name] = array($col_sql_ref, null, null, null, $prototype_type);
            return $cur_columns_data[$column_name];
        }
        $ref_columns = \explode("->", $column_name);
        $parent_class = \get_called_class();
        $parent_pointer_column = $ref_columns[0];
        $parent_pointer = $cur_pointer = $ref_columns[0];
        $parent_alias = $base_model_alias;
        \reset($ref_columns);
        while (false !== $ref_column = \next($ref_columns)) {
            if (substr($parent_pointer_column, -3) == "_id")
                \trigger_error("Syntax error in semantic query: Remove '_id' suffix when resolving pointer colum with the arrow operator.", \E_USER_ERROR);
            $parent_pointer_column .= "_id";
            if (!array_key_exists($parent_pointer_column, $parent_class::getPointerColumns()))
                \trigger_error("Syntax error in semantic query: The pointer column '$parent_pointer_column' is not declared for '$parent_class'!", \E_USER_ERROR);
            if (!isset($cur_columns_data[$parent_pointer][1])) {
                $cur_target_model = $cur_columns_data[$parent_pointer][1] = $parent_class::getTargetModel($parent_pointer_column);
                $cur_alias = $cur_columns_data[$parent_pointer][2] = string\from_index($alias_offset);
                $alias_offset++;
                $target_table = self::classNameToTableName($cur_target_model);
                $cur_columns_data[$parent_pointer][3] = "LEFT JOIN " . db\table($target_table) . " AS $cur_alias ON $cur_alias.id = $parent_alias.$parent_pointer_column";
            } else {
                $cur_target_model = $cur_columns_data[$parent_pointer][1];
                $cur_alias = $cur_columns_data[$parent_pointer][2];
            }
            $cur_pointer .= "->" . $ref_column;
            if (!isset($cur_columns_data[$cur_pointer])) {
                list($column_name, $prototype_type) = $cur_target_model::getColumnPrototype($ref_column);
                $col_sql_ref = $cur_alias . '.' . $column_name;
                $cur_columns_data[$cur_pointer] = array($col_sql_ref, null, null, null, $prototype_type);
            }
            $parent_class = $cur_target_model;
            $parent_alias = $cur_alias;
            $parent_pointer_column = $ref_column;
            $parent_pointer = $ref_column;
        }
        return $cur_columns_data[$cur_pointer];
    }

    private static function getColumnPrototype($column_name) {
        $column_name = static::translateFieldToColumn($column_name);
        if ($column_name != "id") {
            $prototype_types = static::getParsedColumnArray();
            $prototype_type = $prototype_types[$column_name];
            return array($column_name, $prototype_type);
        } else {
            return array("id", null);
        }
    }
    
    protected static function tableNameToClassName($table_name) {
        static $cache = array();
        if (!isset($cache[$table_name])) {
            $table_name = str_replace("__", "\\", $table_name);
            $cls_name = 'melt\\' . string\underline_to_cased($table_name) . "Model";
            $cache[$table_name] = $cls_name;
        }
        return $cache[$table_name];
    }

    /**
     * This function defines how model class names
     * are translated into table names.
     * @param string $class_name
     * @return string
     */
    protected static function classNameToTableName($class_name) {
        static $cache = array();
        if (!isset($cache[$class_name])) {
            $table_name = string\cased_to_underline(substr($class_name, 5, -5));
            $table_name = str_replace("\\", "__", $table_name);
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
        $result = db\query("SELECT v FROM " . db\table('core__metadata') . " WHERE k = " . db\strfy($key));
        $result = db\next_array($result);
        if ($result !== false)
            $result = unserialize($result[0]);
        self::$_metadata_cache[$key] = $result;
        return $result;
    }

    protected static function setMetaData($key, $value) {
        self::$_metadata_cache[$key] = $value;
        db\query("REPLACE INTO " . db\table('core__metadata') . " (k,v) VALUES (" . db\strfy($key) . "," . db\strfy(serialize($value)) . ")");
    }

    /**
     * This function purifies all models. It will delete any column
     * it finds which is not present in the current model layout.
     */
    public static final function purifyAllModels() {
        // This maintenance script can run forever.
        ignore_user_abort(false);
        $model_classes = self::getAllModels();
        echo "\nSearching for redundant/not used columns and removing them...\n\n";
        \ob_flush();
        ignore_user_abort(true);
        set_time_limit(0);
        $found_count = 0;
        foreach ($model_classes as $table_name => $model_class) {
            $column_names = $model_class::getColumnNames();
            $description = db\query("DESCRIBE " . db\table($table_name));
            while (false !== ($column = db\next_assoc($description))) {
                $field_name = $column["Field"];
                if ($field_name == "id")
                    continue;
                // Purify dirty column.
                if (!\array_key_exists($field_name, $column_names)) {
                    db\query("ALTER TABLE " . db\table($table_name) . " DROP COLUMN `" . $field_name . "`");
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
        ignore_user_abort(true);
        set_time_limit(0);
        $cascade_callbacks = array();
        $disconnect_callbacks = array();
        $model_classes = self::getAllModels();
        $family_tree = self::getMetaData("family_tree");
        // Find intersecting ID's.
        echo "\nSearching for corrupt (intersected) primary keys...\n\n";
        foreach ($model_classes as $table_name_a => $model_class_a) {
            $in_table = array();
            foreach ($model_classes as $table_name_b => $model_class_b) {
                if ($table_name_b == $table_name_a)
                    continue;
                $in_table[] = "(id IN (SELECT id FROM " . db\table($table_name_b) . "))";
            }
            $rows = db\query("SELECT id FROM " . db\table($table_name_a) . " WHERE " . implode(" OR ", $in_table));
            $row_count = db\get_num_rows($rows);
            if ($row_count == 0)
                continue;
            echo "\nFound " . $row_count . " instances of " . $model_class_a . " with their PRIMARY KEY corrupt! Repairing...\n\n";
            while (false !== ($row = db\next_array($rows))) {
                $id = intval($row[0]);
                // Insert the row again to gain new id and delete the old corrupt copy.
                db\query("INSERT IGNORE INTO " . db\table($table_name_a) . " SELECT * FROM " . db\table($table_name_a) . " WHERE id = " . $id);
                db\query("DELETE FROM " . db\table($table_name_a) . " WHERE id = " . $id);
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
                if (!isset($family_tree[$target_model]))
                    \trigger_error("Model '$target_model' is out of sync with database.", \E_USER_ERROR);
                if (count($family_tree[$target_model]) == 0)
                    continue;
                $query = array();
                foreach ($family_tree[$target_model] as $model_child_class)
                    $query[] = "($ptr_name NOT IN (SELECT id FROM " . db\table(self::classNameToTableName($model_child_class)) . "))";
                $source_table = self::classNameToTableName($model_class);
                $result = db\query("SELECT id FROM " . db\table($source_table) . " WHERE $ptr_name > 0 AND (" . implode(" OR ", $query) .")");
                if (db\get_num_rows($result) > 0) {
                    // Found pointers that are broken.
                    $ids = array();
                    $instances = array();
                    while (false !== ($col = db\next_array($result))) {
                        $ids[] = $id = $col[0];
                        $instances[$id] = $model_class::selectByID($id);
                    }
                    echo "\nFound " . count($instances) . " instances of " . $model_class . " with their " . $ptr_name . " pointer broken! Repairing...\n\n";
                    // Remove pointers from database (nullify).
                    $table_name = self::classNameToTableName($model_class);
                    db\query("UPDATE " . db\table($table_name) . " SET `$ptr_name` = 0 WHERE id IN (" . implode(",", array_keys($instances)) . ")");
                    // Reflect the broken pointers in memory.
                    foreach ($instances as $instance) {
                        $column = $instance->_cols[$ptr_name];
                        $column->setSQLValue(0);
                        $column->setSyncPoint();
                    }
                    // Index reactions.
                    if ($disconnect_reaction == "CASCADE")
                        $cascade_callbacks += $instances;
                    else if ($disconnect_reaction == "CALLBACK") {
                        foreach ($instances as $id => $instance)
                            $disconnect_callbacks[$id] = array($instance, $ptr_name);
                    }
                }
                // The cascade reaction also implies that the ID is not NULL.
                // Unlink all models with NULL cascade marked pointers.
                if ($disconnect_reaction == "CASCADE") {
                    $instances = $model_class::select()->where($ptr_name)->isntMoreThan(0)->all();
                    if (count($instances) == 0)
                        continue;
                    echo "\nFound " . count($instances) . " instances of " . $model_class . " with their CASCADE pointer " . $ptr_name . " unset! Marking them for cascade unlinking...\n\n";
                    $cascade_callbacks += $instances;
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
        foreach ($disconnect_callbacks as $callback)
            $callback[0]->disconnectCallback($callback[1]);
    }

    /**
     * Go trough all unique indexes, select duplicates and delete them.
     */
    public static function cullAllModels() {
        // This maintenance script can run forever.
        ignore_user_abort(true);
        set_time_limit(0);
        $cascade_callbacks = array();
        $disconnect_callbacks = array();
        $model_classes = self::getAllModels();
        $family_tree = self::getMetaData("family_tree");
        // Find intersecting ID's.
        echo "\nCulling all duplicates that prevents unique indexes from being added...\n\n";
        $found = false;
        foreach ($model_classes as $table_name => $model_class) {
            foreach ($model_class::getIndexes() as $index) {
                if (!$index["is_unique"])
                    continue;
                $columns = \implode(",", $index["columns"]);
                $table = db\table($table_name);
                $duplicates = db\query("SELECT id FROM $table WHERE
                ($columns) IN (
                    SELECT $columns
                    FROM $table
                    GROUP BY $columns HAVING COUNT(*) > 1
                ) AND id NOT IN (
                    SELECT id
                    FROM $table
                    GROUP BY $columns HAVING COUNT(*) > 1
                );");
                $ids = array();
                while (false !== ($row = db\next_array($duplicates)))
                    $ids[] = \intval($row[0]);
                if (\count($ids) == 0)
                    continue;
                // Unlink all duplicates.
                $ids = \implode(",", $ids);
                db\query("DELETE FROM $table WHERE id IN ($ids)");
                $found = true;
            }
        }
        if ($found) {
            echo "\nOne or more duplicates was FORCEFULLY removed, you should "
            . " now run a syncronization followed by a repair to repair any"
            . " damage this could have caused...\n\n";
        } else {
            echo "\nNo duplicates for no indexes found.\n\n";
        }
    }

    private static function validateCoreSeq() {
        // Validate that seq has correct structure.
        $core_seq = db\query("DESCRIBE " . db\table('core__seq'));
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
        // Clear metadata.
        db\run("DROP TABLE " . db\table('core__metadata'));
        db\run("CREATE TABLE " . db\table('core__metadata') . " (`k` varchar(16) NOT NULL PRIMARY KEY, `v` BLOB NOT NULL)");
        $creating_sequence = !in_array(db\config\PREFIX . 'core__seq', db\get_all_tables());
        if (!$creating_sequence) {
            // Validate that seq still has one row.
            $result = db\query("SELECT count(*) FROM " . db\table('core__seq'));
            $row = db\next_array($result);
            $creating_sequence = ($row[0][0] != 1);
            if (!$creating_sequence)
                $creating_sequence = !self::validateCoreSeq();
        }
        $sequence_max = 1;
        $model_classes = self::getAllModels();
        foreach ($model_classes as $table_name => $model_class) {
            // Syncronize this model.
            $parsed_col_array = $model_class::getParsedColumnArray();
            db\sync_table_layout_with_model($table_name, $parsed_col_array, $model_class::getIndexes());
            // If creating sequence, record max.
            if ($creating_sequence) {
                $max_result = db\next_array(db\query("SELECT MAX(id) FROM " . db\table($table_name)));
                $max_result = intval($max_result[0]);
                if ($sequence_max < $max_result)
                    $sequence_max = $max_result;
            }
        }
        // Track all ancestors of all models.
        $family_tree = array();
        foreach ($model_classes as $model_class) {
            // A self relation allows us to loop trough all models that IS
            // that model, and itself IS that model.
            $family_tree[$model_class][] = $model_class;
            foreach (class_parents($model_class) as $model_parent) {
                if ($model_parent == 'melt\Model')
                    continue;
                $family_tree[$model_parent][] = $model_class;
            }
        }
        self::setMetaData("family_tree", $family_tree);
        // Record pointers for pointer map.
        $pointer_map = array();
        foreach ($model_classes as $model_class) {
            foreach ($model_class::getPointerColumns(true) as $col_name => $target_model) {
                foreach ($family_tree[$target_model] as $target_child_class)
                    $pointer_map[$target_child_class][] = array($model_class, $col_name);
            }
        }
        self::setMetaData("pointer_map", $pointer_map);
        if ($creating_sequence) {
            // Need to create the sequence.
            db\run("DROP TABLE " . db\table('core__seq'));
            db\query("CREATE TABLE " . db\table('core__seq') . " (id BIGINT PRIMARY KEY NOT NULL)");
            db\query("INSERT INTO " . db\table('core__seq') . " VALUES (" . (intval($sequence_max) + 1) . ")");
        }
        // Configure table engines.
        $config_engine = db\storage_engine();
        if ($config_engine === null)
            return;
        $fn_get_db_engines = function() {
            $result = db\query("SHOW TABLE STATUS");
            $db_engines = array();
            while (false !== ($row = db\next_assoc($result)))
                $db_engines[$row["Name"]] = \strtolower($row["Engine"]);
            return $db_engines;
        };
        $db_engines = $fn_get_db_engines();
        $table_names = \array_merge(\array_keys($model_classes), array("core__seq", "core__metadata"));
        foreach ($table_names as $table_name) {
            $prefixed_table_name = db\config\PREFIX . $table_name;
            if (!isset($db_engines[$prefixed_table_name]))
                \trigger_error("Table `$prefixed_table_name` was unexpectedly not found when reflecting database.", \E_USER_ERROR);
            if ($db_engines[$prefixed_table_name] != $config_engine) {
                db\query("ALTER TABLE " . db\table($table_name) . " ENGINE = $config_engine");
                // Verify that table engine was altered correctly.
                $db_engines = $fn_get_db_engines();
                if ($db_engines[$prefixed_table_name] != $config_engine)
                    \trigger_error("Altering engine of table `$prefixed_table_name` to $config_engine failed!", \E_USER_ERROR);
            }
        }
    }

    /**
     * Returns an array of all model classes in application and modules.
     * Note: This looks up and loads all classes so it's slow the first time
     * it runs.
     * @return array
     */
    public static final function getAllModels($include_abstract = false) {
        return internal\get_all_classes("model", "models", $include_abstract, "__");
    }

}