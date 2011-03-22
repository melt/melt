<?php namespace nmvc\qmi;

/**
 * An interface to one or more model instances.
 */
class ModelInterface {
    private $instances = array();
    private $components = array();
    private $setters = array();
    private $autolinks = array();
    private $interface_name = null;
    private $success_url;
    private $creating = true;
    private $default_style;
    private $time_created;

    /**
     * @param string $interface_name Name of interface (it's identifier).
     * Used for selecting validation and components to return for models.
     * Also used for selecting callback.
     * @param string $default_style
     * @param string $success_url Where default handler should redirect
     * interface on success.
     */
    public function __construct($interface_name, $default_style = "default", $success_url = null) {
        if (!\preg_match('#^\w+(\\\\\w+)?$#', $interface_name))
            \trigger_error(__CLASS__ . " error: Unexpected \$interface_name format: $interface_name", \E_USER_ERROR);
        $this->interface_name = $interface_name;
        $this->default_style = (string) $default_style;
        $this->success_url = is_null($success_url)? url(REQ_URL): $success_url;
        $this->time_created = new \DateTime();
    }

    /**
     * Returns form start tag for interface.
     */
    public function startForm($extra_attributes = array()) {
        $attributes = array();
        foreach ($extra_attributes as $key => $value)
            $attributes[] = "$key=\"" . escape($value) .'"';
        return '<form enctype="multipart/form-data" action="' . url(REQ_URL) . '" method="post" ' . implode(" ", $attributes) . '>';
    }

    /**
     * Finalizes interface data and returns it ina a hidden input tag
     * and a closing form tag.
     */
    public function finalizeForm($auto_submit = true, $auto_delete = false) {
        // Convert instance object references to id|class references.
        foreach ($this->instances as &$instance)
            $instance = array($instance->getID(), get_class($instance), $instance->isVolatile());
        $qmi_data = \nmvc\string\simple_crypt(gzcompress(serialize(array(
            $this->success_url, $this->instances, $this->components
            , $this->setters, array_values($this->autolinks)
            , $this->interface_name, $this->time_created
        ))));
        $html = '<div><input type="hidden" name="_qmi" value="' . $qmi_data . '" />';
        if ($auto_submit) {
            $msg = $this->creating? __("Add New"): __("Save Changes");
            $html .= '<input type="submit" value="' . $msg . '" />';
        }
        if ($auto_delete && !$this->creating) {
            $delete = __("Delete");
            $html .= '<input name="_qmi_auto_delete_button" type="submit" value="'
            . $delete . '" onclick="javascript: return confirm(\'Are you sure?\');" />';
        }
        $html .= '</div></form>';
        return $html;
    }

    public static function _clearInvalidData() {
        // Clearing the invalidation data as it was used this request.
        unset($_SESSION["qmi_invalid"]);
    }

    /**
     * Returns an instance key that identifies an instance as it is
     * stored in the database and not as qmi\getInstanceKey
     * (as represented in-memory).
     */
    public static function getDatabaseInstanceKey(\nmvc\Model $instance) {
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
        $instance_key = \spl_object_hash($instance);
        if (!\array_key_exists($instance_key, $this->instances))
            $this->instances[$instance_key] = $instance;
        return $instance_key;
    }

    /**
     * @return boolean
     */
    private function instanceAdded(\nmvc\Model $instance = null) {
        $instance_key = spl_object_hash($instance);
        return \array_key_exists($instance_key, $this->instances);
    }

    /**
     * Attaches a relation (or lack of, if target_model is null)
     * to the interface that will be stored after successful validation.
     * Neither the source model, nor the target model
     * is required to be linked at the time of calling this function.
     * If they aren't qmi will automatically insert and store them,
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
     * using qmi\ModelInterface::attachRelation().
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
            $this->setters[$instance_key][$field_name] = $value;
        }
    }

    /**
     * This function loops trough all attributes for the given instance
     * and calls qmi\attachChange() for every non stored change.
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
     * This function attaches the fields the given model specifies
     * for the interface name this ModelInterface was constructed with.
     * It then fetches the html components for that model instance and
     * renders them using the specified html style.
     * @param UserInterfaceProvider $instance Model instance to interface.
     * @param string $field_set_name Identifier of field set to pass
     * to uiGetInterface.
     * @param string $style Set to non null to override style used to render
     * interface. Is used to find the renderering view at the path
     * /qmi2/{$style}
     * @param array $additional_view_data
     * @return string HTML for interface.
     */
    public function getInterface(UserInterfaceProvider $instance, $field_set_name = null, $style = null, $additional_view_data = array()) {
        if (!($instance instanceof \nmvc\Model))
            \trigger_error('\$instance must be instance of model!', \E_USER_ERROR);
        if ($style !== null && !\is_string($style))
            \trigger_error('\$style must be string or null!', \E_USER_ERROR);
        if (!\is_array($additional_view_data))
            \trigger_error('\$additional_view_data must be array!', \E_USER_ERROR);
        if ($instance->isLinked())
            $this->creating = false;
        $html_components = array();
        $invalidation_data = array();
        // Storing all instance changes on success.
        $this->attachChanges($instance);
        $instance_key = $this->getMemoryInstanceKey($instance);
        $db_instance_key = self::getDatabaseInstanceKey($instance);
        if (isset($_SESSION["qmi_invalid"]['name'])
        && $_SESSION["qmi_invalid"]['name'] == $this->interface_name
        && isset($_SESSION["qmi_invalid"]['data'][$db_instance_key])) {
            $invalidation_data = $_SESSION["qmi_invalid"]['data'][$db_instance_key];
            // A clear of this invalidation data is now pending.
            static $pending_data_clear = false;
            if (!$pending_data_clear) {
                $pending_data_clear = true;
                register_shutdown_function(array(__CLASS__, "_clearInvalidData"));
            }
        }
        // Using index of instance as a basis for consistant, multi-request
        // spanning component id/name.
        $instance_index = 0;
        foreach ($this->instances as $instance_in) {
            if ($instance_in === $instance)
                break;
            $instance_index++;
        }
        // Loop trough fields in interface.
        $ui_fields = $instance->uiGetInterface($this->interface_name, $field_set_name);
        if (!is_array($ui_fields))
            \trigger_error("No '" . $this->interface_name . "', '$field_set_name' interface returned by " . \get_class($instance) . ". (Undeclared?)", \E_USER_ERROR);
        foreach (\array_keys($ui_fields) as $field_name) {
            if (\strlen($field_name) == 0 || $field_name[0] == "_")
                continue;
            if (!$instance->hasField($field_name))
                \trigger_error("Error in interface '" . $this->interface_name . "' for " . \get_class($instance) . ": '$field_name' is not a valid field/column name!", \E_USER_ERROR);
            if (substr($field_name, -3) == "_id")
                \trigger_error("Error in interface '" . $this->interface_name . "' for " . \get_class($instance) . ": Field syntax '$field_name' is reserved for id access. Pointer fields must be passed without '_id' suffix.", \E_USER_ERROR);
            // Generate the html key/id.
            $component_id = "qmiid" . \substr(\sha1($field_name . "," . $instance_index . "," . $this->interface_name), 0, 12);
            // Generate the component interface.
            if (isset($invalidation_data['values'][$field_name]))
                $instance->$field_name = $invalidation_data['values'][$field_name];
            $type = $instance->type($field_name);
            // Make sure sub resolved fields have their parent instances attached.
            if ($type->parent != $instance)
                $this->attachChanges($type->parent);
            $component_interface = $type->getInterface($component_id);
            // If an interface(s) was returned, output it.
            if (\is_string($component_interface) && \strlen($component_interface) > 0)
                $component_interfaces = array($component_interface);
            else if (\is_array($component_interface))
                $component_interfaces = \array_values($component_interface);
            else
                $component_interfaces = array();
            foreach ($component_interfaces as $index => $component_interface) {
                // Append error label if one is specified.
                $component_error = isset($invalidation_data['errors'][$field_name])? $invalidation_data['errors'][$field_name]: null;
                // Prefix "_" prevents collision with other fields when using types with multiple interfaces.
                $html_components_key = ($index == 0)? $field_name:  "_" . $field_name . "_" . ($index + 1);
                $field_label = isset($ui_fields[$html_components_key])? $ui_fields[$html_components_key]: null;
                $html_components[$html_components_key] = new HtmlComponent($component_interface, $field_label, $component_error, $component_id, $type);
            }
            // Registering the component.
            $this->components[$component_id] = array($instance_key, $field_name);
        }
        // Find the style for the interface.
        if ($style == null && isset($ui_fields["_style"]))
            $style = $ui_fields["_style"];
        if ($style == null)
            $style = $this->default_style;
        if (\strlen($style) == 0)
            \trigger_error("Style is empty!", \E_USER_ERROR);
        // Render the actual interface.
        return \nmvc\View::render("/qmi/" . $style . "_interface", \array_merge($additional_view_data, array("components" => $html_components)));
    }
    
    /**
     * Handles post data returned from a generated interface.
     * @return array
     */
    public static function _interface_callback() {
        if (!\array_key_exists("_qmi", $_POST))
            return;
        $ajax_submit = array_key_exists("_qmi_ajax_submit", $_POST) && $_POST["_qmi_ajax_submit"] == true;
        $qmi_data = \nmvc\string\simple_decrypt($_POST["_qmi"]);
        if ($qmi_data === false) {
            \nmvc\messenger\redirect_message(REQ_URL, __("The action failed, your session might have timed out. Please try again."));
            return;
        }
        list($success_url, $instance_keys, $components, $setters, $autolinks, $interface_name, $time_created) = unserialize(gzuncompress($qmi_data));
        // Fetch all instances and translate all instance keys to their new spl object hashes.
        $instances = array();
        foreach ($instance_keys as $old_instance_key => $instance_values) {
            list($id, $name, $is_volatile) = $instance_values;
            if ($id > 0) {
                $instance = $name::selectByID($id);
                if ($instance === null) {
                    \nmvc\messenger\redirect_message(REQ_URL, __("Action failed, one or more of the entries you edited has been deleted."));
                    return;
                }
            } else
                $instance = new $name($is_volatile);
            $instances[$old_instance_key] = $instance;
        }
        $instance_fields = array();
        if (isset($_POST['_qmi_auto_delete_button'])) {
            $is_deleting = true;
        } else {
            $is_deleting = false;
            // Process all setters.
            foreach ($setters as $instance_key => $fields) {
                foreach ($fields as $field_name => $value)
                    $instances[$instance_key]->$field_name = $value;
            }
            // Connect the instances that should be connected.
            foreach ($autolinks as $autolink) {
                list($source_model_key, $target_model_key, $pointer_field_name) = $autolink;
                $pointer_model = $instances[$source_model_key];
                $target_model = $target_model_key !== null? $instances[$target_model_key]: null;
                $pointer_model->$pointer_field_name = $target_model;
            }
            // Read all components from post data (overwriting setters).
            foreach ($components as $component_id => $component) {
                list($instance_key, $field_name) = $component;
                $instances[$instance_key]->type($field_name)->readInterface($component_id);
                $instance_fields[$instance_key][$field_name] = $component_id;
            }
        }
        // Extract callback from interface name.
        $pos = \strpos($interface_name, '\\');
        if ($pos !== false) {
            $callback_module = \substr($interface_name, 0, $pos);
            $callback_method = \substr($interface_name, $pos + 1);
        } else {
            $callback_module = 'qmi';
            $callback_method = $interface_name;
        }
        $callback_method = "ic_$callback_method";
        $callback_class = 'nmvc\\' . $callback_module . '\\InterfaceCallback';
        if (!\class_exists($callback_class))
            \trigger_error(__METHOD__ . " error: The callback class '$callback_class' does not exist!", \E_USER_ERROR);
                if (!is($callback_class, 'nmvc\qmi\InterfaceCallback'))
            \trigger_error(__METHOD__ . " error: The callback class '$callback_class' does not extend 'nmvc\qmi\InterfaceCallback'!", \E_USER_ERROR);
        if (!is($callback_class, $callback_class . "_app_overrideable"))
            \trigger_error(__METHOD__ . " error: The callback class '$callback_class' is not declared overridable by the responsible module!", \E_USER_ERROR);
        $callback_class = new $callback_class($interface_name, $instances, $instance_fields, $is_deleting, $success_url, $ajax_submit, $time_created);
        $callback_class->$callback_method();
        if ($ajax_submit) {
            $data = array("success" => true, "unlinked" => false, "errors" => array());
            foreach ($instances as $instance) {
                if (!$instance->isLinked()) {
                    $data["unlinked"] = true;
                    break;
                }
            }
            \nmvc\request\send_json_data($data);
        } else
            \nmvc\request\redirect($callback_class->getSuccessUrl());
    }
}
