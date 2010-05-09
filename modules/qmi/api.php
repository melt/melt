<?php namespace nmvc\qmi;

/**
 * Prints the interface.
 * @param array $html_components Output from getComponents()
 */
function print_interface($html_components) {
    foreach ($html_components as $html_name => $component) {
        if (is_array($component)) {
            $label = "<label for=\"$html_name\">" . escape($component[1]) . "</label>";
            echo  $label . " " . $component[0];
        } else
            echo $component;
    }
}

/**
 * Prints the interface in a table.
 * @param array $html_components Output from getComponents()
 */
function print_table_interface($html_components) {
    echo "<table>";
    foreach ($html_components as $html_name => $component) {
        echo "<tr>";
        if (is_array($component)) {
            $label = "<label for=\"$html_name\">" . escape($component[1]) . "</label>";
            echo "<td>$label</td><td>" . $component[0] . "</td>";
        } else
            echo "<td></td><td>$component</td>";
        echo "</tr>";
    }
    echo "</table>";
}

/**
 * Returns a link that can perform an action on an instance.
 * @param Model $instance Model instance to operate on.
 * @param string $action The actions 'delete' and 'copy' is currently supported.
 * @param string $url Where to go after the action. NULL simply follows referer.
 */
function get_action_link($instance, $action = "delete", $url = null) {
    if (!is_a($instance, '\nmvc\Model'))
        throw new \Exception("Cannot make a delete link to a non model object!");
    $id = $instance->getID();
    if ($id <= 0)
        return null;
    $model_name = get_class($instance);
    $qmi_data = \nmvc\string\simple_crypt(serialize(array($id, $model_name, $action, $url)));
    return url("/qmi/actions/set/$qmi_data");
}
