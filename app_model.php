<?php namespace nmvc;

/** Application specific model. */
abstract class AppModel extends Model {

    /**
     * @desc Overidable event. Called on model instances before they are stored.
     * @param boolean $is_linked True if the model instance is currently linked in the database. False if it's about to be INSERTED.
     */
    public function beforeStore($is_linked) { }

    /**
     * @desc Overidable event. Called on model instances after they are stored.
     * @param boolean $is_linked True if the model instance was linked in the database before the store. False if it was INSERTED just now.
     */
    public function afterStore($was_linked) { }

    /**
     * @desc Overidable event. Called on model instances that is about to be unlinked in the database.
     */
    public function beforeUnlink() { }

    /**
     * @desc Overidable event. Called on model instances after they have been unlinked in the database.
     */
    public function afterUnlink() { }

    /**
     * Override this function to implement application
     * level model access control.
     */
    public function accessing() { }

    /** Override this function to initialize members of this model. */
    public function initialize() { }

    /**
     * Validates the current data. If invalid, returns an array of all fields
     * name => reason mapped, otherwise, returns an empty array.
     * Designed to be overriden.
     * @return array All invalid fields, name => reason mapped.
     */
    public function validate() {
        return array();
    }

    /**
     * @desc Overidable function. Called on model instances when one of their pointers
     * is turning invalid because the instance that pointer points to is about
     * to be unlinked from the database.
     * The default implementation is to clear that pointer (set it to 0)
     * but this can be overridden to any particular garbage collection behavior.
     * @desc Note: Always clearing broken pointers to zero is useful because
     * you can find out if a pointer points nowhere with a simple == 0.
     * @desc This function is NOT called on instances that are currently beeing unlinked
     * in the stack. This is because GC is considered unneccessary on already
     * deleted instances. Also, it enables you to unlink any instances freely
     * in this function, in any model graph, without getting infinite loops.
     */
    public function gcPointer($field_name) {
        $this->$field_name = 0;
        $this->store();
    }
}
