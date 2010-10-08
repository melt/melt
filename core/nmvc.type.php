<?php namespace nmvc;

/** A type defines what a model column stores, and how. */
abstract class Type {
    /** @var string The key of this type instance. */
    protected $key = null;
    /** @var mixed The value of this type instance. */
    protected $value = null;
    /** @var Model The parent of this type instance. */
    public $parent = null;
    /** @var mixed The original value that was set from SQL. */
    protected $original_value = null;

    /** Returns the value from the last sync point. */
    public function getSyncPoint() {
        return $this->original_value;
    }

    /**
     * Called to indicate that the type was synced so
     * that it can measure changes made from this point.
     */
    public function setSyncPoint() {
        $this->original_value = $this->value;
    }

    /** Returns TRUE if this type has changed since the last syncronization. */
    public function hasChanged() {
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

    /** Constructs this typed field with this column name. */
    public function __construct($column_name) {
        $this->key = $column_name;
    }

    /** Event responsible for preparing the SQL value to be stored in the database. */
    public function prepareSQLValue() {}

    /** Returns the data in a SQLized storeable form. */
    abstract public function getSQLValue();

    public function setSQLValue($value) {
        $this->value = $value;
    }

    /** Should return the SQL type that this input is stored in. */
    abstract public function getSQLType();

    /**
     * HTML representation of type instance.
     * Just prints the value by default.
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
}
