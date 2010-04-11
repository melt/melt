<?php

namespace nanomvc\qmi;

/**
 * Internal function. Initializes and returns the QMI key used in this session.
 * @return string Session QMI key.
 */
function get_qmi_key() {
    // Generate and append a qmi field that can safely bounce the interface data.
    if (!isset($_SESSION['qmi_key']) || strlen($_SESSION['qmi_key']) < 16)
        $_SESSION['qmi_key'] = \nanomvc\string\random_alphanum_str(16);
    return $_SESSION['qmi_key'];
}

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
    $qmi_key = get_qmi_key();
    if (!is_a($instance, "Model"))
        throw new \Exception("Cannot make a delete link to a non model object!");
    $id = $instance->getID();
    if ($id <= 0)
        return null;
    $model_name = get_class($instance);
    $qmi_data = string\simple_crypt(serialize(array($id, $model_name, $action, $url)));
    return url("/qmi/actions/set/$qmi_data");
}
