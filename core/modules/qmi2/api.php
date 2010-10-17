<?php namespace nmvc\qmi2;

/**
 * Prints a generic interface. A generic example of how output from
 * qmi2\ModelInterface::attachFields() can be handled.
 * @param array $html_components Output from qmi2\ModelInterface::attachFields()
 * @param array $labels Field names mapped to their respective labels.
 * @return void
 */
function print_interface($html_components, $labels, $as_table = false) {
    $data = compact("html_components", "labels");
    if ($as_table)
        \nmvc\View::render("/qmi2/interface", $data, false, false);
    else
        \nmvc\View::render("/qmi2/table_interface", $data, false, false);
}


/**
 * Returns a link that can perform an action on a model instance or model.
 * @param mixed $model Model instance to operate on or a class to forward request to.
 * @param string $action The actions 'delete' and 'copy' is currently implemented.
 * Unknown actions are treated as functions with the same name (on the model).
 * The funtion must be static if a model class was supplied in the first argument.
 * @param string $url Where to go after the action. NULL returns to this URL.
 * @param array $arguments List of arguments to pass to the function.
 * @return string URL that is only valid for this client session.
 */
function get_action_link($model = null, $action = "delete", $url = null, $arguments = array()) {
    if (is_object($model)) {
        if (!is_a($model, '\nmvc\Model'))
            throw new \Exception("Cannot make a delete link to a non model object!");
        $id = $model->getID();
        if ($id <= 0)
            return null;
        $model_name = get_class($model);
    } else {
        if (!is($model, 'nmvc\Model'))
            throw new \Exception("The supplied class '$model' is not a nmvc\\Model!");
        $model_name = $model;
        $id = 0;
    }
    if ($url == null)
        $url = url(REQ_URL);
    $qmi_data = \nmvc\string\simple_crypt(gzcompress(serialize(array($id, $model_name, $action, $url, array_values($arguments))), 9));
    return url("/qmi2/actions/set/$qmi_data");
}
