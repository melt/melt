<?php

/**
 * A model that automatically unlinks itself whenever any of
 * the instances it's pointers points to gets unlinked.
 */
class GCModel extends Model {

    public function gcPointer($field_name) {
        // This might seem a bit emo but hey...
        $this->unlink();
    }

    // Oh, sorry. Where you expecting more code?
}
?>