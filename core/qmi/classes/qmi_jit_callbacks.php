<?php namespace melt\qmi;

interface QmiJitCallbacks {

    /**
     * Called before the instance is jit-attached to the model interface.
     * @param ModelInterface $model_interface The current ModelInterface.
     */
    function qmiJitAttach(ModelInterface $model_interface);

    /**
     * Called before the instance is jit-detached from the model interface.
     * @param ModelInterface $model_interface The current ModelInterface.
     */
    function qmiJitDetach(ModelInterface $model_interface);
}