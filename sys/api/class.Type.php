<?php

abstract class Type {
    /**
    *@desc Should return the SQL type that this input is stored in.
    */
    abstract public function getSQLType();

    /**
    * @desc Returns the data in a SQLized storeable form.
    * @param string $data The data to SQLize.
    */
    abstract public function SQLize($data);

    /**
    * @desc Should return an interface component that handles modification of the data in a form.
    * @param string $label The label of the component.
    * @param string $data The data to HTMLize.
    * @param string $name The HTML name of the component.
    */
    abstract public function getInterface($label, $data, $name);

    /**
    * @desc Writes component data to a representation in HTML.
    */
    abstract public function write($value);

    /**
    * @desc Reads the component data from POST and possibly sets the value to something different.
    */
    abstract public function read($name, &$value);
}

?>
