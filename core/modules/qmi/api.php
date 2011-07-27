<?php namespace melt\qmi;

/**
 * Prints a generic interface. A generic example of how output from
 * qmi\ModelInterface::attachFields() can be handled.
 * @param array $html_components Output from qmi\ModelInterface::attachFields()
 * @param array $labels Field names mapped to their respective labels.
 * @return void
 */
function print_interface($html_components, $labels, $as_table = false) {
    $data = compact("html_components", "labels");
    if ($as_table)
        \melt\View::render("/qmi/interface", $data, false, false);
    else
        \melt\View::render("/qmi/table_interface", $data, false, false);
}


/**
 * Returns a link that can perform an action on a model instance or model.
 * @param mixed $model Model instance to operate on or a class to forward request to.
 * @param string $action The actions 'delete' and 'copy' is currently implemented.
 * Unknown actions are treated as functions with the same name (on the model).
 * The funtion must be static if a model class was supplied in the first argument.
 * @param string $url Where to go after the action. NULL returns to this URL.
 * @param array $arguments List of arguments to pass to the function.
 * @param boolean $secure Set to false to not restrict generated links to
 * the current logged in user.
 * @return string URL that is only valid for this client session.
 */
function get_action_link($model = null, $action = "delete", $url = null, $arguments = array(), $secure = true) {
    if (is_object($model)) {
        if (!is_a($model, '\melt\Model'))
            throw new \Exception("Cannot make a delete link to a non model object!");
        $id = $model->getID();
        if ($id <= 0)
            return null;
        $model_name = get_class($model);
    } else {
        if (!is($model, 'melt\Model'))
            throw new \Exception("The supplied class '$model' is not a melt\\Model!");
        $model_name = $model;
        $id = 0;
    }
    if ($url == null)
        $url = url(REQ_URL);
    $uid = $secure? id(\melt\userx\get_user()): 0;
    $qmi_data = \melt\string\simple_crypt(gzcompress(serialize(array($id, $model_name, $action, $url, array_values($arguments), $uid)), 9));
    if (\strlen($qmi_data) > 1020)
        \trigger_error("Generating action link that is larger than 1K. This link might not be supported on all browsers/servers.", \E_USER_NOTICE);
    return url("/qmi/actions/set/$qmi_data");
}
