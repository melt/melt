<?php namespace nmvc;

/** A type defines what a model column stores, and how. */
abstract class Type {
    /** @var string The key of this type instance. */
    private $key = null;
    /** @var Model The parent of this type instance. */
    private $parent = null;
    /** @var The type of storage used for the field data.
     * One of: NON_INDEXED, VOLATILE, INDEXED, INDEXED_UNIQUE. */
    private $storage_type = null;
    
    public function __construct() {}

    public function __clone() {
        $this->parent = null;
    }

    public final function __get($name) {
        return $this->$name;
    }

    public final function __set($name, $value) {
        if (\array_key_exists($name, array("key" => 1, "parent" => 1, "storage_type" => 1))) {
            if ($this->$name !== null)
                \trigger_error("Trying to write read-only property '$name'.", \E_USER_ERROR);
        }
        $this->$name = $value;
    }
    
    /** @var mixed The value of this type instance. */
    protected $value = null;
    /** @var mixed The original value that was set from SQL. */
    protected $original_value = null;

    /**
     * Returns the value from the last sync point.
     * @return mixed
     */
    public function getSyncPoint() {
        return $this->original_value;
    }

    /**
     * Called to indicate that the type was synced so
     * that it can measure changes made from this point.
     * @return void
     */
    public function setSyncPoint() {
        $this->original_value = $this->value;
    }

    /**
     * Returns TRUE if this type has changed since the last syncronization.
     * @return boolean
     */
    public function hasChanged() {
        return $this->original_value != $this->value;
    }

    /**
     * Returns the value of this typed field.
     * @return mixed
     */
    public function get() {
        return $this->value;
    }
    /**
     * Sets the value of this typed field.
     * @param mixed $value
     * @return void
     */
    public function set($value) {
        $this->value = $value;
    }

    /**
     * Returns true if this type can safely be assigned this value.
     * Types should force values into the closest representation when being
     * set as this follows PHP standard convention. If types are picky with
     * what values they set however, they MUST override this and return
     * false for those values.
     * @param boolean $value
     * @return boolean
     */
    public function takes($value) {
        return true;
    }

    /**
     * Responsible for HTML representation of type instance.
     * @return string HTML
     */
    public function __toString() {
        return escape($this->value);
    }

    /**
     * Should return any amount of interface components that handles
     * modification of the data in a form.
     * @param string $name The HTML name/id of the component.
     * @return mixed An array of components, a string of one component
     * or null/false for no component.
     */
    abstract public function getInterface($name);

    /**
     * Reads the component data from POST and possibly sets the value to something different.
     * If this function returns a string, that will be handled as a field error
     * that will be merged with whatever the model validate() returns.
     * @param string $name The HTML name of the component.
     */
    abstract public function readInterface($name);

    /**
     * Event responsible for preparing the SQL value
     * to be stored in the database.
     */
    public function prepareSQLValue() {}

    /**
     * Responsible for converting internal value of this type into SQL token
     * in an injective, deterministic manner.
     * @return string SQL token
     */
    abstract public function getSQLValue();

    /**
     * Responsible for setting the type from a SQL result.
     * @param mixed $value
     * @return void
     */
    public function setSQLValue($value) {
        $this->value = $value;
    }

    /**
     * Responsible for returning the MySQL type that this type uses for
     * database storage of its value.
     * @return string MySQL type (e.g. BIGINT)
     */
    abstract public function getSQLType();
}
