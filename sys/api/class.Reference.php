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


    public final function getSQLType() {
        return "int";
    }
    public final function getSQLValue() {
        return intval($this->value);
    }

}

?>