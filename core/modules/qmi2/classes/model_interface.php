<?php namespace nmvc\qmi2;

/**
 * An interface to a model.
 * Designed to be used directly in the view.
 */
class ModelInterface {
    private $instances = array();
    private $components = array();
    private $setters = array();
    private $autolinks = array();
    private $final_callback = null;
    private $success_url;
    private $creating = true;

    /**
     * Constructs this interface. Prints a HTML form start tag.
     */
    public function __construct($success_url = null, $extra_attributes = array()) {
        // Initialize interface data
        $this->success_url = is_null($success_url)? url(REQ_URL): $success_url;
        $attributes = array();
        foreach ($extra_attributes as $key => $value)
            $attributes = "$key=\"" . escape($value) .'"';
        echo '<form enctype="multipart/form-data" action="' . url(REQ_URL) . '" method="post" ' . implode(" ", $attributes) . '>';
    }

    public static function _clearInvalidData() {
        // Clearing the invalidation data as it was used this request.
        unset($_SESSION["qmi_invalid"]);
    }

    /**
     * Returns an instance key that identifies an instance as it is
     * stored in the database and not as qmi2\getInstanceKey
     * (as represented in-memory).
     */
    private static function getDatabaseInstanceKey(\nmvc\Model $instance) {
        // This is unique because class names cannot start with numbers.
        return ($instance->id > 0? $instance->id: "0")  . get_class($instance);
    }

    /**
     * Returns an instance key that represents an instance in memory
     * and proceeds to store it as an instance this interface is dealing with.
     */
    private function getMemoryInstanceKey(\nmvc\Model $instance = null) {
        if ($instance === null)
            return null;
        $instance_key = spl_object_hash($instance);
        if (!in_array($instance_key, $this->instances))
            $this->instances[$instance_key] = $instance;
        return $instance_key;
    }

    /**
     * @returns bool
     */
    private function instanceAdded(\nmvc\Model $instance = null) {
        $instance_key = spl_object_hash($instance);
        return in_array($instance_key, $this->instances);
    }

    /**
     * Attaches a relation (or lack of, if target_model is null)
     * to the interface that will be stored after successful validation.
     * Neither the source model, nor the target model
     * is required to be linked at the time of calling this function.
     * If they aren't qmi2 will automatically insert and store them,
     * then link them afterwards.
     * @param \nmvc\Model $source_model Relation source. The model with the
     * pointer.
     * @param \nmvc\Model $target_model Relation target or NULL to reset
     * pointer.
     * @param string $explicit_pointer_name If source model has more
     * than one pointer fields that takes target model, or if you are
     * specifying that any existing relation should be removed, then you
     * will have to specificy the name of the pointer field explicitly.
     * @return void
     */
    public function attachRelation(\nmvc\Model $source_model, \nmvc\Model $target_model = null, $explicit_pointer_name = null) {
        // Validate the attached relation.
        $pointer_columns = $source_model->getPointerColumns(false);
        if (substr($explicit_pointer_name, -3) == "_id")
            trigger_error("Field syntax '$explicit_pointer_name' is reserved for id access. Pointer fields must be passed without '_id' suffix.", \E_USER_ERROR);
        if ($explicit_pointer_name == null) {
            if ($target_model === null)
                trigger_error("When attching a relation that should be removed, explicit target pointer name must be specified.", \E_USER_ERROR);
            // Find the target pointer field.
            $pointer_name = null;
            foreach ($pointer_columns as $pointer_name => $target_class) {
                if (is($target_model, $target_class)) {
                    if ($pointer_name !== null)
                        trigger_error("The source model " . get_class($source_model) . " has more than one field that points to a " . get_class($target_model) . "! ($pointer_name, $pointer_name)", \E_USER_ERROR);
                    $pointer_name = $pointer_name;
                }
            }
            if ($pointer_name === null)
                trigger_error("The source model " . get_class($source_model) . " has no pointer fields that points to a " . get_class($target_model) . "!", \E_USER_ERROR);
        } else {
            // Validate the explicit target pointer field.
            $pointer_name = $explicit_pointer_name;
            if (!isset($pointer_columns[$pointer_name]))
                trigger_error("The field $pointer_name does not exist on source model " . get_class($source_model) . " or is not a pointer field!");
            if ($target_model !== null && !is($target_model, $pointer_columns[$pointer_name]))
                trigger_error("The pointer " . get_class($source_model) . "->$pointer_name does not take a " . get_class($target_model) . "!");
        }
        $source_instance_added = $this->instanceAdded($source_model);
        $target_instance_added = $this->instanceAdded($target_model);
        $source_key = $this->getMemoryInstanceKey($source_model);
        $target_key = $this->getMemoryInstanceKey($target_model);
        $relation_key = sha1("$source_key/$pointer_name", true);
        // If adding already specified relation, return.
        if (array_key_exists($relation_key, $this->autolinks) && $this->autolinks[$relation_key][1] == $target_key)
            return;
        // Identify this relation with a unique key and quit if already specified
        // that this relation should be added.
        $this->autolinks[$relation_key] = array(
            $source_key,
            $target_key,
            $pointer_name,
        );
        // If source or target does not exist, store them on success.
        if (!$source_model->isLinked() && !$source_instance_added)
            $this->attachChanges($source_model);
        if ($target_model !== null && !$target_model->isLinked() && !$target_instance_added)
            $this->attachChanges($target_model);
    }

    /**
     * Attaches an array of changes that should be stored for this instance
     * on validation success. The array is field name => new value mapped.
     * The field names must be pointer types. Relations should be attached
     * using qmi2\ModelInterface::attachRelation().
     * @param Model $instance
     * @param array $values
     * @return void
     */
    public function attachChangeArray(\nmvc\Model $instance, $values) {
        // Reading the setters from the rest of the arguments.
        $instance_key = $this->getMemoryInstanceKey($instance);
        foreach ($values as $field_name => $value) {
            if (!$instance->hasField($field_name))
                trigger_error("'$field_name' is not a valid field/column name!", \E_USER_ERROR);
            else if (is($instance->type($field_name), 'nmvc\core\PointerType'))
                trigger_error("'$field_name' is a pointer attachChangeArray does not take pointer fields! Use attachRelation() instead.", \E_USER_ERROR);
            $this->setters[] = array($instance_key, $field_name, $value);
        }
    }

    /**
     * This function loops trough all attributes for the given instance
     * and calls qmi2\attachChange() for every non stored change.
     * Additionally, if it finds pointers that have changed,
     * those relations will be automatically attached.
     * Relations to unlinked models are also counted as changes.
     * In effect, any relations you have from the instance to unlinked
     * models will be preserved and the unlinked models linked on validation
     * success.
     * @param Model $instance
     * @return void
     */
    public function attachChanges(\nmvc\Model $instance) {
        // Make sure the instance is stored.
        $this->getMemoryInstanceKey($instance);
        $changed_fields = array();
        foreach ($instance->getColumns() as $column_name => $column) {
            if (is($column, 'nmvc\core\PointerType')) {
                $target = $column->get();
                if ($column->hasChanged() || (is_object($target) && is($target, 'nmvc\Model') && !$target->isLinked())) {
                    $column_name = substr($column_name, 0, -3);
                    $this->attachRelation($instance, $target, $column_name);
                }
            } else if ($column->hasChanged())
                $changed_fields[$column_name] = $column->get();
        }
        if (count($changed_fields) > 0)
            $this->attachChangeArray($instance, $changed_fields);
    }

    /**
     * This function attaches the specified fields on the instance
     * to this model interface and returns HTML interfaces for each of them.
     * qmi2\ModelInterface::attachChanges() is also
     * automatically called for the given instance, so you can model changes
     * directly on the instanc before generating the form.
     * See qmi2\print_interface() for an example on how to treat the output.
     * @param \nmvc\Model $instance Model instance to interface.
     * @param array $fields Names of the fields to get interfaces for.
     * @param string $component_css_class CSS class used for each and
     * every one of the divs outputted as components.
     * @param string $invalid_span_css_class CSS class used in HTML
     * for "invalid" spans added if resubmitting form.
     * @return array Component items attached to the fields of the form
     * array(html interface, unique key).
     */
    public function attachFields(\nmvc\Model $instance, $fields = array(), $component_css_class = "qmi_component", $invalid_span_css_class = "qmi_invalid_label") {
        if ($instance->isLinked())
            $this->creating = false;
        $html_components = array();
        $invalidation_data = array();
        // Storing all instance changes on success.
        $this->attachChanges($instance);
        $instance_key = $this->getMemoryInstanceKey($instance);
        $db_instance_key = self::getDatabaseInstanceKey($instance);
        if (isset($_SESSION["qmi_invalid"][$db_instance_key])) {
            $invalidation_data = $_SESSION["qmi_invalid"][$db_instance_key];
            // A clear of this invalidation data is now pending.
            static $pending_data_clear = false;
            if (!$pending_data_clear) {
                $pending_data_clear = true;
                register_shutdown_function(array(__CLASS__, "_clearInvalidData"));
            }
        }
        // Adding all components.
        foreach ($fields as $field_name) {
            if (!$instance->hasField($field_name))
                trigger_error("'$field_name' is not a valid field/column name!", \E_USER_ERROR);
            if (substr($field_name, -3) == "_id")
                trigger_error("Field syntax '$field_name' is reserved for id access. Pointer fields must be passed without '_id' suffix.", \E_USER_ERROR);
            // Generate the html key/id.
            $component_html_key = "n" . \nmvc\string\random_alphanum_str(7);
            // Generate the component interface.
            if (isset($invalidation_data['values'][$field_name]))
                $instance->$field_name = $invalidation_data['values'][$field_name];
            $component_interface = $instance->type($field_name)->getInterface($component_html_key);
            // If an interface(s) was returned, output it.
            if (is_string($component_interface) && strlen($component_interface) > 0)
                $component_interfaces = array($component_interface);
            else if (is_array($component_interface))
                $component_interfaces = array_values($component_interface);
            else
                $component_interfaces = array();
            foreach ($component_interfaces as $key => $component_interface) {
                // Append error label if one is specified.
                if (isset($invalidation_data['errors'][$field_name])) {
                    $component_interface .= "<span class=\"$invalid_span_css_class\">"
                    . $invalidation_data['errors'][$field_name]
                    . "</span>";
                }
                // Returning the interface.
                $component_interface = "<div class=\"$component_css_class\">"
                . $component_interface . "</div>";
                $html_components_key = ($key == 0)? $field_name: $field_name . "_" . $key;
                $html_components[$html_components_key] = array($component_interface, $component_html_key);
            }
            // Registering the component.
            $this->components[$component_html_key] = array($instance_key, $field_name);
        }
        return $html_components;
    }

    /**
     * Sets the final callback for the model interface.
     * The final callback will be ran after the success methods.
     * @param callback $callback A classic callback (not closure) that will be invoked on completion.
     * @param array $arguments
     * @return void
     */
    public function setFinalCallback($callback, $arguments = array()) {
        if (!\is_callable($callback) || \is_object($callback))
            \trigger_error(__METHOD__ . " got non-callable/non-serializable callback!", \E_USER_ERROR);
        if (!\is_array($arguments))
            \trigger_error(__METHOD__ . " got non array \$arguments argument!", \E_USER_ERROR);
        $this->final_callback = array($callback, $arguments);
    }

    /**
     * Sets the final callback for the model interface.
     * @return string
     */
    public function getFinalCallback() {
        return $this->final_callback;
    }

    /**
     * Ends the interface. Prints a html form end tag.
     */
    public function finalize($auto_submit = true, $auto_delete = false) {
        // Convert instance object references to id|class references.
        foreach ($this->instances as &$instance)
            $instance = array($instance->getID(), get_class($instance));
        $qmi_data = \nmvc\string\simple_crypt(gzcompress(serialize(array($this->success_url, $this->instances, $this->components, $this->setters, array_values($this->autolinks), $this->final_callback))));
        echo '<div><input type="hidden" name="_qmi" value="' . $qmi_data . '" />';
        if ($auto_submit) {
            $msg = $this->creating? __("Create"): __("Save Changes");
            echo '<input type="submit" value="' . $msg . '" />';
        }
        if ($auto_delete && !$this->creating) {
            $delete = __("Delete");
            echo '<input name="_qmi_auto_delete_button" type="submit" value="'
            . $delete . '" onclick="javascript: return confirm(\'Are you sure?\');" />';
        }
        echo '</div></form>';
    }

    /**
     * Do not call directly.
     */
    public static function _interface_callback() {
        $qmi_data = \nmvc\string\simple_decrypt(@$_POST['_qmi']);
        if ($qmi_data === false) {
            \nmvc\messenger\redirect_message(REQ_URL, __("The action failed, your session might have timed out. Please try again."));
            return;
        }
        list($success_url, $instance_keys, $components, $setters, $autolinks, $final_callback) = unserialize(gzuncompress($qmi_data));
        // Array mapping old instance keys to new db keys.
        $instance_db_keys = array();
        // Fetch all instances and translate all instance keys to their new spl object hashes.
        $instances = array();
        foreach ($instance_keys as $old_instance_key => $instance_values) {
            list($id, $name) = $instance_values;
            if ($id > 0) {
                $instance = $name::selectByID($id);
                if ($instance === null) {
                    \nmvc\messenger\redirect_message(REQ_URL, __("Action failed, one or more of the entries you edited has been deleted."));
                    return;
                }
            } else
                $instance = $name::insert();
            $instances[$old_instance_key] = $instance;
            $instance_db_keys[$old_instance_key] = self::getDatabaseInstanceKey($instance);
        }
        $iid = null;
        if (isset($_POST['_qmi_auto_delete_button'])) {
            // Deleting everything.
            foreach ($instances as $instance)
                $instance->unlink();
        } else {
            // Process all setters.
            foreach ($setters as $setter) {
                list($instance_key, $field_name, $value) = $setter;
                $instances[$instance_key]->$field_name = $value;
            }
            // Connect the instances that should be connected.
            foreach ($autolinks as $autolink) {
                list($source_model_key, $target_model_key, $pointer_field_name) = $autolink;
                $pointer_model = $instances[$source_model_key];
                $target_model = $target_model_key !== null? $instances[$target_model_key]: null;
                $pointer_model->$pointer_field_name = $target_model;
            }
            $instance_fields = array();
            // Read all components from post data (overwriting setters).
            foreach ($components as $component_name => $component) {
                list($instance_key, $field_name) = $component;
                $instances[$instance_key]->type($field_name)->readInterface($component_name);
                $instance_fields[$instance_key][$field_name] = 1;
            }
            // Validate all instances.
            $invalidation_data = array();
            foreach ($instances as $instance_key => $instance) {
                $ret = $instance->validate();
                // Validation not returning array = validation success.
                if (!is_array($ret))
                    continue;
                // No components for instance = validation success.
                if (!isset($instance_fields[$instance_key]))
                    continue;
                // Not interested in invalid fields we have not created interfaces for.
                $ret = array_intersect_key($ret, $instance_fields[$instance_key]);
                // Empty array = validation success.
                if (count($ret) == 0)
                    continue;
                // Store this invalidation data so it can be forwarded.
                $instance_db_key = $instance_db_keys[$instance_key];
                $invalidation_data[$instance_db_key]['errors'] = $ret;
            }
            if (count($invalidation_data) > 0) {
                // Fetch all interface values and return them.
                foreach ($instances as $instance_key => $instance)
                foreach ($instance as $field_name => $component) {
                    $value = $component->get();
                    if (is_object($value))
                        $value = $component->getSQLValue();
                    $instance_db_key = $instance_db_keys[$instance_key];
                    $invalidation_data[$instance_db_key]['values'][$field_name] = $value;
                }
                // Store invalid and reload this URL.
                $_SESSION['qmi_invalid'] = $invalidation_data;
                \nmvc\messenger\redirect_message(REQ_URL, __("Validation failed. Please check your input."));
            }
            // Store all instances.
            foreach ($instances as $instance_key => $instance) {
                $instance->store();
                if ($iid === null)
                    $iid = $instance->getID();
            }
        }
        if ($final_callback !== null) {
            // Invoke final callback if set.
            list($callback, $arguments) = $final_callback;
            // Compile instances as first parameter in arguments.
            $callback_instances = array();
            foreach ($instances as $instance)
                $callback_instances[\get_class($instance)][] = $instance;
            \array_unshift($arguments, $callback_instances);
            call_user_func_array($callback, $arguments);
        }
        // Redirect to the success url and don't display success message (overkill).
        \nmvc\request\reset();
        $success_url = str_replace("{id}", $iid, $success_url);
        \nmvc\request\redirect($success_url);
    }
}
