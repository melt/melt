<?php namespace nmvc\core;

/**
 * Universial pointers can point to any model. (See Object pointers in Java)
 * The downside is that they aren't "normal" pointers, so they cannot be
 * used for child lookups and cannot garbage collect themselves.
 */
class UniversialReferenceType extends \nmvc\AppType {
    protected $value = array(null, 0);

    /** Resolves this pointer and returns the model it points to. */
    public function get() {
        if (!is_array($this->value) || count($this->value) != 2)
            return null;
        $id = intval($this->value[1]);
        if ($id <= 0)
            return null;
        $target_model = $this->value[0];
        if (!is_subclass_of($target_model, 'nmvc\Model'))
            return null;
        $model = $target_model::selectByID($id);
        if (!is_object($model))
            return null;
        return $model;
    }

    public function set($value) {
        if ($value === null) {
            $this->value = array(null, 0);
        } else if ($value instanceof \nmvc\Model) {
            $id = $value->getID();
            $this->value = array(get_class($value), $id);
        } else
            trigger_error("Attempted to set UniversialReferenceType to non Model instance.", \E_USER_ERROR);
    }

    public function setSQLValue($value) {
        $this->value = unserialize($value);
    }

    public function getSQLType() {
        return "TINYTEXT";
    }

    public function getSQLValue() {
        return \nmvc\db\strfy(serialize($this->value));
    }
    
    public function getInterface($name) { }

    public function readInterface($name) { }

}
