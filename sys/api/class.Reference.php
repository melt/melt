<?php

abstract class Reference extends Type {
    public $to_model;

    /**
    * @desc Constructs this reference to the specified model
    */
    public final function Reference($name, $id, $to_model) {
        parent::Type($name, $id);
        $this->to_model = $to_model;
    }

    /**
    * @desc Resolves this reference and returns the model it points to.
    */
    public final function ref() {
        static $last_id = null, $last_resolve;
        $id = intval($this->value);
        if ($id === $last_id)
            return $last_resolve;
        else
            return $last_resolve = call_user_func(array($this->to_model, "selectByID"), $id);
    }

    // Using overloading to turn the ref function into a variable for convenience.
    public final function __isset($name) {
        return $name == "ref";
    }
    public final function __get($name) {
        if ($name == "ref")
            return $this->ref();
        else
            throw new Exception("Attempted to read from a non existing variable '$name' on reference type.");
    }
    public final function __set($name, $value) {
        if ($name == "ref") {
            if (is_object($value)) {
                // Make sure this is a type of model we are pointing to.
                if (!is_a($value, $this->to_model))
                    throw new Exception("Attempted to set a reference to an incorrect object."
                    . "The reference expects " . $this->to_model . " objects, you gave it a " . get_class($value) . " object.");
                $this->value = intval($value->getID());
            } else {
                // Assuming this is an ID.
                $this->value = intval($value);
            }
        } else
            throw new Exception("Attempting to write to a non existing variable '$name' on reference type.");
    }

    public final function getSQLType() {
        return "int";
    }
    public final function getSQLValue() {
        return intval($this->value);
    }

}

?>