<?php namespace melt\qmi;

class ActionsController extends \melt\AppController {
    /**
     * Callback for qmi actions.
     * @see get_action_link
     */
    public function set($data) {
        $data = \melt\string\simple_decrypt($data);
        if ($data === false)
            \melt\request\show_404();
        list($id, $model_name, $action, $url, $arguments, $uid) = unserialize(gzuncompress($data));
        if ($uid > 0 && $uid !== id(\melt\userx\get_user()))
            \melt\request\show_xyz(403);
        if ($id > 0) {
            $instance = call_user_func(array($model_name, "selectByID"), $id);
            if ($instance === null)
                \melt\request\show_404();
            switch ($action) {
            case "delete":
                $instance->unlink();
                break;
            case "copy":
                clone $instance;
                $instance->store();
                $url = str_replace("{id}", $instance->getID(), $url);
                break;
            default:
                if (!method_exists($instance, $action))
                    throw new \Exception("Invalid argument passed to get_action_link. Unknown action: '$action'");
                \call_user_func_array(array($instance, $action), $arguments);
            }
        } else {
            // Static function.
            \call_user_func_array(array($model_name, $action), $arguments);
        }
        if (\melt\request\is_ajax())
            \melt\request\send_json_data(true);
        else
            \melt\request\redirect($url);
    }

    /**
     * Ajax callback that mutates QMI data to a new state.
     */
    public function mutate() {
        $blob = \file_get_contents("php://input");
        $blobs = \explode(",", $blob);
        if (\count($blobs) < 2)
            \melt\request\show_invalid();
        $interface_blob = $blobs[0];
        $operations = array();
        for ($i = 1; $i < \count($blobs); $i++) {
            if ($i > 1 && @$blobs[$i][0] === "@") {
                // JIT instance ID.
                $operations[$i - 1][2] = \substr($blobs[$i], 1);
                continue;
            }
            $operation = @\unserialize(\gzuncompress(\melt\string\simple_decrypt($blobs[$i])));
            if (!\is_array($operation))
                \melt\request\show_invalid();
            $operations[$i] = $operation;
            $operations[$i][2] = null;
        }
        $model_interface = ModelInterface::unserialize($interface_blob);
        if (!($model_interface instanceof ModelInterface))
            \melt\request\show_invalid();
        $model_interface->__jsMutate($operations);
        exit;
    }
}