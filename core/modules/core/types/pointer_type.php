<?php namespace melt\core;

/** A very special type that abstracts a pointer. */
class PointerType extends \melt\AppType {
    /** @var string Target model class name. Set by constructor. */
    protected $target_model;
    protected $disconnect_reaction;

    /**
     * Returns the model target of this Pointer.
     * @return string Class name.
     */
    public final function getTargetModel() {
        return $this->target_model;
    }

    /**
     * Returns the configured disconnect reaction for this pointer.
     * @return string One of 'SET NULL', 'CASCADE', 'CALLBACK'.
     */
    public function getDisconnectReaction() {
        return $this->disconnect_reaction;
    }

    /**
     * Returns true if this pointer field takes the specified model.
     * @param mixed $model Class name or model instance.
     * @return boolean
     */
    public final function canTake($model) {
        return is($model, $this->target_model);
    }

    /**
     * Returns a HTML representation of this pointer.
     * It will try to generate a link for the model if it can.
     */
    public function __toString() {
        $target = $this->get();
        if (!is_object($target))
            return "—";
        $html = (string) $target;
        return $html;
    }

    /**
     * Constructs this typed field with this column name.
     * @param $target_model The model class name pointer points too with
     * the initial implicit 'melt\' removed.
     * @param $disconnect_reaction Currently supported are 'SET NULL',
     * 'CASCADE' and 'CALLBACK'. See manual for more information.
     */
    public function __construct($target_model, $disconnect_reaction = "SET NULL") {
        $target_model = 'melt\\' . $target_model;
        if (!class_exists($target_model) || !is_subclass_of($target_model, 'melt\Model'))
            trigger_error("Attempted to declare a pointer pointing to a non existing model '$target_model'.");
        $this->target_model = $target_model;
        // Read disconnect reaction.
        $reaction_map = array(
            "SET NULL" => 1,
            "CASCADE" => 1,
            "CALLBACK" => 1,
        );
        $disconnect_reaction = strtoupper($disconnect_reaction);
        if (!isset($reaction_map[$disconnect_reaction]))
            trigger_error("The disconnection reaction $disconnect_reaction is unknown. Expected one of 'SET NULL', 'CASCADE', 'CALLBACK'.", \E_USER_ERROR);
        $this->disconnect_reaction = $disconnect_reaction;
    }

    /**
     * Resolves this pointer by ID.
     * @return integer
     */
    public function getID() {
        if (is_object($this->value))
            return $this->value->id;
        else
            return $this->value > 0? $this->value: 0;
    }

    /**
     * The basic Melt Framework pointer does not have an interface by default.
     * Simply returns a string representation of its value.
     */
    public function getInterface($name) { }

    /**
     * The basic Melt Framework pointer does not have an interface
     * by default. Function does nothing.
     */
    public function readInterface($name) { }

    /**
     * Returns the model this pointer points to or null if
     * no such model exists.
     * @return \melt\Model
     */
    public function get() {
        // If this is not yet an memory object pointer,
        // resolve it by setting to same id.
        if (is_integer($this->value)) {
            if ($this->value > 0) {
                $target_model = $this->target_model;
                $this->value = $target_model::selectByID($this->value);
                if ($this->value === null && !REQ_IS_CORE_CONSOLE)
                    trigger_error("The database state is corrupt, please run repair. Pointer referencing $target_model has ID set, but target does not exist in database. Setting pointer to NULL.", \E_USER_WARNING);
            } else
                $this->value = null;
        }
        // Return null object instead of null in special cases where linked and unset cascade to prevent fatal errors etc.
        if ($this->value == null && $this->disconnect_reaction == "CASCADE" && $this->parent->isLinked())
            return new NullObject();
        return $this->value;
    }

    /**
     * Contains pointers that represent in-memory object relations
     * (non-id relations) that are mapped from pointer target spl hash
     * and the pointer type spl hash (the unique in memory relation).
     * This makes it possible to reverse lookup in-memory pointers.
     * @var array
     */
    public static $memory_object_pointer_backlinks = array();

    /**
     * Memory object pointers are reset when cloning to keep
     * memory object pointer backlink structure intact.
     */
    public function __clone() {
        parent::__clone();
        $value = $this->value;
        if (!is_object($value))
            return;
        $this->value = null;
        $this->set($value);
    }

    /**
     * Internal function. Called to clear incomming memory object pointers
     * when flushing instance cache. DO NOT CALL.
     * @internal
     */
    public static function _clearIncommingMemoryObjectPointers() {
        self::$memory_object_pointer_backlinks = array();
    }

    /**
     * For some instance, returns its incomming memory object pointers.
     * These are represented as arrays where the first index is the
     * instance that has the in memory object pointer and where the
     * second index is the name of the pointer field that points to the
     * given instance. The keys or order of the returned array is undefined.
     * @return array Array of array(\melt\Type, string)
     */
    public static function getIncommingMemoryObjectPointers(\melt\Model $for_instance) {
        $key = spl_object_hash($for_instance);
        if (!array_key_exists($key, self::$memory_object_pointer_backlinks))
            return array();
        else
            return self::$memory_object_pointer_backlinks[$key];
    }

    /**
     * Sets the model this pointer points to.
     * @param mixed $value ID of model or model instance.
     * @return void
     */
    public function set($value) {
        // If value is an ID, resolve target first.
        // This is to ensure data integrity. If it turns out to be a
        // performance bump in some cases, it can simply be
        //resolved by implementing model JIT data fetching.
        if (is_integer($value)) {
            if ($value > 0) {
                $id = $value;
                $target_model = $this->target_model;
                $value = $target_model::selectByID($id);
                if ($value === null)
                    trigger_error("Setting a $target_model pointer to non existing ID: $id (Assuming NULL)", \E_USER_WARNING);
            } else
                $value = null;
        } else if (is_object($value)) {
            // Make sure this is a type of model we are pointing to.
            if (!is_a($value, $this->target_model))
                trigger_error("Attempted to set a pointer to an incorrect object. The pointer expects " . $this->target_model . " objects, it was given a " . get_class($value) . " object.", \E_USER_ERROR);
        } else if (!is_null($value))
            trigger_error("Pointer expecting Model object or integer ID. Got: " . gettype($value), \E_USER_ERROR);
        // Return on no change.
        if ($value === $this->value)
            return;
        // Unset any previous in memory object pointer backlink.
        if (is_object($this->value)) {
            unset(self::$memory_object_pointer_backlinks[spl_object_hash($this->value)][spl_object_hash($this)]);
            if (isset(self::$memory_object_pointer_backlinks[spl_object_hash($this->value)]) && count(self::$memory_object_pointer_backlinks[spl_object_hash($this->value)]) == 0)
                unset(self::$memory_object_pointer_backlinks[spl_object_hash($this->value)]);
        }
        // Store backlink of this memory object pointer.
        if (!is_null($value))
            self::$memory_object_pointer_backlinks[spl_object_hash($value)][spl_object_hash($this)] = array($this, $this->key);
        // Store memory object pointer.
        $this->value = $value;
    }

    public function takes($value) {
        $target_model = $this->target_model;
        if (\is_integer($value)) {
            if ($value <= 0)
                return true;
            return $target_model::selectByID($value) !== null;
        }
        if (\is_object($value))
            return \is_a($value, $target_model);
        else if (\is_null($value))
            return true;
        return false;
    }
    
    public function getSQLType() {
        return "int(16) unsigned";
    }

    public function getSQLValue() {
        return $this->getID();
    }

    public function prepareSQLValue() {
        // Cascade pointers may not be NULL in database so automatically
        // store before returning if memory-only pointer,
        // otherwise trigger error.
        if ($this->disconnect_reaction == "CASCADE" && $this->getID() == 0) {
            if (!\is_object($this->value))
                \trigger_error("Trying to store CASCADE pointer (" . \get_class($this->parent) . "->\$" . $this->key . ") in database as NULL (illegal value for CASCADE pointer)", \E_USER_ERROR);
            // Tracking cascade store track to prevent circularity.
            static $cascade_store_stack = array();
            $target_hash = \spl_object_hash($this->value);
            if (isset($cascade_store_stack[$target_hash]))
                \trigger_error("CASCADE storing has reached a circular loop. Circular CASCADE pointer object model graphs are illegal. Your models are broken.", \E_USER_ERROR);
            $cascade_store_stack[$target_hash] = $this->value;
            $this->value->store();
            unset($cascade_store_stack[$target_hash]);
        }
    }


    public function setSQLValue($value) {
        $this->value = intval($value);
    }
    
    public function getSQLName() {
        return $column_name . "_id";
    }

    // As internal value can be both object and id, we need to override
    // the default hasChanged implementation.

    public function setSyncPoint() {
        $this->original_value = $this->getID();
    }

    /**
     * Returns true if the SQL backend pointer ID changes. So will return
     * true for all changes except if changing pointer target from one
     * unlinked model instance to another unlinked model instance.
     * If pointer points to an unlinked model instance it has "changed"
     * in a memory context sense anyway so testing for this case is simple.
     */
    public function hasChanged() {
        return $this->original_value != $this->getID();
    }

}
