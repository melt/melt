<?php namespace nanomvc\qmi;

/**
 * Prints the interface.
 * @param array $html_components Output from make_interface()
 * @param boolean $wrap_in_form Set to true to wrap output in form tags.
 */
function print_interface($html_components) {
    foreach ($html_components as $component)
        echo $component;
}

/**
 * Returns a link that can perform an action on an instance.
 * @param Model $instance Model instance to operate on.
 * @param string $action The actions 'delete' and 'copy' is currently supported.
 * @param string $url Where to go after the action. NULL simply follows referer.
 */
function get_action_link($instance, $action = "delete", $url = null) {
    if (!is_a($instance, '\nanomvc\Model'))
        throw new \Exception("Cannot make a delete link to a non model object!");
    $id = $instance->getID();
    if ($id <= 0)
        return null;
    $model_name = get_class($instance);
    $qmi_data = \nanomvc\string\simple_crypt(serialize(array($id, $model_name, $action, $url)));
    return url("/qmi/actions/set/$qmi_data");
}
