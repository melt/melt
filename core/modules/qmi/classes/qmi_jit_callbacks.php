<?php namespace nmvc\qmi;

interface QmiJitCallbacks {

    /**
     * Called before the instance is jit-attached to the model interface.
     * @param ModelInterface $model_interface The current ModelInterface.
     */
    abstract function qmiJitAttach(ModelInterface $model_interface);

    /**
     * Called before the instance is jit-detached from the model interface.
     * @param ModelInterface $model_interface The current ModelInterface.
     */
    abstract function qmiJitDetach(ModelInterface $model_interface);
}