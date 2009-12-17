<?php

abstract class Type {
    protected $name;
    protected $value;

    /**
    * @desc Returns the value of this typed field.
    */
    public final function get() {
        return $this->value;
    }
    /**
    * @desc Sets the value of this typed field.
    */
    public final function set($value) {
        $this->value = $value;
    }

    /**
    * @desc Constructs this typed field with this initialized name and value.
    */
    public function Type($name, $value = null) {
        $this->name = $name;
        $this->value = $value;
    }

    /**
    * @desc Converts the internal PHP value of this typed field to a printable HTML representation.
    */
    abstract public function __toString();

    /**
    *@desc Should return the SQL type that this input is stored in.
    */
    abstract public function getSQLType();

    /**
    * @desc Returns the data in a SQLized storeable form.
    */
    abstract public function getSQLValue();

    /**
    * @desc Should return an interface component that handles modification of the data in a form.
    * @param string $label The label of the component.
    * @param string $name The HTML name of the component.
    */
    abstract public function getInterface($label);

    /**
    * @desc Reads the component data from POST and possibly sets the value to something different.
    */
    abstract public function readInterface();
}

?>
