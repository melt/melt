<?php namespace melt\qmi;

/**
 * An interface to one or more model instances.
 */
class ModelInterface {
    private $instances = array();
    private $new_instance_tags = array();
    private $setters = array();
    private $components = array();
    private $autolinks = array();
    private $jit_references = array();
    private $interface_name = null;
    private $success_url;
    private $creating = true;
    private $default_style;
    private $time_created;
    private $identity;
    private $continuum_steps = array();

    const INSTANCE_JIT_REFERENCE = -1;

    private $prev_continuum_data = array();

    /**
     * @param string $interface_name Name of interface (its identifier).
     * Used for selecting validation and components to return for models.
     * Also used for selecting callback.
     * @param string $default_style
     * @param string $success_url Where default handler should redirect
     * interface on success.
     */
    public function __construct($interface_name, $default_style = "default", $success_url = null) {
        if ($interface_name === null)
            return;
        if (!\preg_match('#^\w+(\\\\\w+)?$#', $interface_name))
            \trigger_error(__CLASS__ . " error: Unexpected \$interface_name format: $interface_name", \E_USER_ERROR);
        $this->interface_name = $interface_name;
        $this->default_style = (string) $default_style;
        $this->success_url = is_null($success_url)? url(REQ_URL): $success_url;
        $this->time_created = new \DateTime();
        $this->identity = "qi" . \melt\string\random_alphanum_str(10);
    }

    public function hasInstance(UserInterfaceProvider $instance) {
        $instance_key = \spl_object_hash($instance);
        return \array_key_exists($instance_key, $this->instances);
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
        $html = '<div><input type="hidden" id="' . $this->identity . '" name="_qmi" value="' . $this->serialize() . '" />';
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

    /**
     * Returns a serializeable instance reference.
     */
    private static function getInstanceReference(\melt\Model $instance, $store_changes = false) {
        $changed_fields = array();
        if ($store_changes) {
            foreach ($instance->getColumns() as $column_name => $column) {
                if (!$column->hasChanged())
                    continue;
                $changed_fields[$column_name] = ($column instanceof \melt\core\PointerType)? $column->getID(): $column->get();
            }
        }
        return array($instance->getID(), \get_class($instance), $instance->isVolatile(), $changed_fields);
    }

    private static function getReferencedInstance($reference, $ignore_stored_changes = false) {
        list($id, $name, $is_volatile, $changed_fields) = $reference;
        if ($id > 0) {
            $instance = $name::selectByID($id);
            if ($instance === null)
                return false;
        } else {
            $instance = new $name($is_volatile);
        }
        if (!$ignore_stored_changes) {
            foreach ($changed_fields as $column => $value)
                $instance->$column = $value;
        }
        return $instance;
    }

    /**
     * Returns an instance key that represents an instance in memory
     * and proceeds to store it as an instance this interface is dealing with.
     */
    private function getMemoryInstanceKey(\melt\Model $instance = null) {
        if ($instance === null)
            return null;
        $instance_key = \spl_object_hash($instance);
        if (!\array_key_exists($instance_key, $this->instances))
            $this->instances[$instance_key] = $instance;
        return $instance_key;
    }

    /**
     * Returns an instance key offset that can be used to construct
     * consistant, multi-request spanning component id/name.
     */
    private function getInstanceKeyOffset(\melt\Model $instance = null) {
        $this->getMemoryInstanceKey($instance);
        $instance_index_offset = 0;
        foreach ($this->instances as $instance_in) {
            if ($instance_in === $instance)
                break;
            $instance_index_offset++;
        }
        return $instance_index_offset;
    }

    /**
     * Attaches an unlinked instance if it's not attached, tags it and returns it.
     * If instance is already attached and tagged with this name - returns
     * the already tagged instance.
     * @param Model $instance
     * @param string $instance_tag
     */
    public function tagNewInstance(\melt\Model $instance, $instance_tag) {
        \assert(!$instance->isLinked());
        if (isset($this->new_instance_tags[$instance_tag]))
            return $this->instances[$this->new_instance_tags[$instance_tag]];
        $this->new_instance_tags[$instance_tag] = $this->getMemoryInstanceKey($instance);
        return $instance;
    }

    /**
     * @return boolean
     */
    private function instanceAdded(\melt\Model $instance = null) {
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
     * @param \melt\Model $source_model Relation source. The model with the
     * pointer.
     * @param \melt\Model $target_model Relation target or NULL to reset
     * pointer.
     * @param string $explicit_pointer_name If source model has more
     * than one pointer fields that takes target model, or if you are
     * specifying that any existing relation should be removed, then you
     * will have to specificy the name of the pointer field explicitly.
     * @return void
     */
    public function attachRelation(\melt\Model $source_model, \melt\Model $target_model = null, $explicit_pointer_name = null) {
        // Validate the attached relation.
        $pointer_columns = $source_model->getPointerColumns(false, true);
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
    public function attachChangeArray(\melt\Model $instance, $values) {
        // Reading the setters from the rest of the arguments.
        $instance_key = $this->getMemoryInstanceKey($instance);
        foreach ($values as $field_name => $value) {
            if (!$instance->hasField($field_name))
                trigger_error("'$field_name' is not a valid field/column name!", \E_USER_ERROR);
            else if (is($instance->type($field_name), 'melt\core\PointerType'))
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
    public function attachChanges(\melt\Model $instance) {
        // Make sure the instance is stored.
        $this->getMemoryInstanceKey($instance);
        $changed_fields = array();
        foreach ($instance->getColumns() as $column_name => $column) {
            if ($column instanceof \melt\core\PointerType) {
                $target = $column->get();
                if ($column->hasChanged() || (($target instanceof \melt\Model) && !$target->isLinked())) {
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
     * @param mixed $field_set_name Identifier of field set to pass
     * to uiGetInterface.
     * @param mixed $style Set to non null to override style used to render
     * interface. Is used to find the renderering view at the path
     * /qmi2/{$style}
     * @param array $additional_view_data
     * @return mixed HTML for interface.
     */
    public function getInterface(UserInterfaceProvider $instance, $field_set_name = null, $style = null, array $additional_view_data = array()) {
        \assert($instance instanceof \melt\Model);
        \assert($style === null || \is_string($style));
        $html_components = array();
        // Storing all instance changes on success.
        $this->attachChanges($instance);
        $ui_fields = $instance->uiGetInterface($this->interface_name, $field_set_name);
        if (!is_array($ui_fields))
            \trigger_error("No '" . $this->interface_name . "', '$field_set_name' interface returned by " . \get_class($instance) . ". (Undeclared?)", \E_USER_ERROR);
        $html_components = $this->getInterfaceComponents($instance, \array_keys($ui_fields), $ui_fields);
        // Find the style for the interface.
        if ($style == null && isset($ui_fields["_style"]))
            $style = $ui_fields["_style"];
        if ($style == null)
            $style = $this->default_style;
        if (\strlen($style) == 0)
            \trigger_error("Style is empty!", \E_USER_ERROR);
        // Render the actual interface.
        return \melt\View::render("/qmi/" . $style . "_interface", \array_merge($additional_view_data, array("components" => $html_components, "interface" => $this)));
    }

    /**
     * Returns an array of HtmlComponent objects for the instance and field.
     * The component is also "attached" and "registred" internaly on
     * the Model Interface.
     * @param \melt\Model $instance
     * @param mixed $fields String or array of field names.
     * @return array[HtmlComponent]
     */
    public function getInterfaceComponents(\melt\Model $instance, $fields, $field_labels = array()) {
        if ($instance->isLinked())
            $this->creating = false;
        if (!\is_array($fields))
            $fields = array($fields);
        $instance_key = $this->getMemoryInstanceKey($instance);
        $instance_key_offset = $this->getInstanceKeyOffset($instance);
        // Get the invalidation data.
        $invalidation_data = array();
        if (isset($_SESSION["qmi_invalid"][$this->interface_name])) {
            $invalidation_data = $_SESSION["qmi_invalid"][$this->interface_name];
            // A clear of the invalidation data is now pending.
            static $pending_data_clear = false;
            if (!$pending_data_clear) {
                $pending_data_clear = true;
                register_shutdown_function(function() {
                    unset($_SESSION["qmi_invalid"]);
                });
            }
        }
        foreach ($fields as $field_name) {
            if (\strlen($field_name) == 0 || $field_name[0] == "_")
                continue;
            if (!$instance->hasField($field_name))
                \trigger_error("Error in interface '" . $this->interface_name . "' for " . \get_class($instance) . ": '$field_name' is not a valid field/column name!", \E_USER_ERROR);
            if (substr($field_name, -3) == "_id")
                \trigger_error("Error in interface '" . $this->interface_name . "' for " . \get_class($instance) . ": Field syntax '$field_name' is reserved for id access. Pointer fields must be passed without '_id' suffix.", \E_USER_ERROR);
            // Generate the html key/id.
            $component_id = "qmiid" . \substr(\sha1($field_name . "," . $instance_key_offset . "," . $this->interface_name), 0, 12);
            // Pre-fill the field with the previous, possibly errorneous, value.
            if (isset($invalidation_data['values'][$component_id]))
                $instance->$field_name = $invalidation_data['values'][$component_id];
            // Make sure sub resolved fields have their parent instances attached.
            $type = $instance->type($field_name);
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
                $component_error = ($index == 0 && isset($invalidation_data['errors'][$component_id]))? $invalidation_data['errors'][$component_id]: null;
                // Prefix "_" prevents collision with other fields when using types with multiple interfaces.
                $html_components_key = ($index == 0)? $field_name:  "_" . $field_name . "_" . ($index + 1);
                $field_label = isset($field_labels[$html_components_key])? $field_labels[$html_components_key]: null;
                $html_components[$html_components_key] = new HtmlComponent($component_interface, $field_label, $component_error, $component_id, $type);
            }
            // Registering the component.
            $this->components[$component_id] = array($instance_key, $field_name);
        }
        // Return the interface components.
        return $html_components;
    }

    /**
     * Completly detaches the instance from the interface. All references
     * to the instance is removed, including changes and relations from other
     * instances to this instance. Returns all component id's that where
     * removed as a result of the operation.
     * @param \melt\Model $instance
     * @return array
     */
    public function detachInstance(\melt\Model $instance) {
        $instance_key = $this->getMemoryInstanceKey($instance);
        // Remove all references to the instance.
        $detached_components = array();
        foreach ($this->components as $component_id => $component) {
            if ($component[0] !== $instance_key)
                continue;
            unset($this->components[$component_id]);
            $detached_components[$component_id] = $component_id;
        }
        unset($this->setters[$instance_key]);
        foreach ($this->autolinks as $autolink_key => $autolink) {
            if ($autolink[0] === $instance_key || $autolink[1] === $instance_key)
                unset($this->autolinks[$autolink_key]);
        }
        foreach ($this->jit_references as $jit_reference => $jit_instance_key) {
            if ($jit_instance_key === $instance_key)
                unset($this->jit_references[$jit_reference]);
        }
        unset($this->instances[$instance_key]);
        return $detached_components;
    }

    /**
     * Detaches the interface and returns the component id's that was detached.
     * Only detaches the set of components that belong to the interface,
     * not the actual instance.
     * @see ModelInterface::detachInstance() to detach the actual instance.
     * @see ModelInterface::getInterface() for more details.
     * @param UserInterfaceProvider $instance
     * @param string $field_set_name
     * @return array
     */
    public function detachInterface(UserInterfaceProvider $instance, $field_set_name = null) {
        \assert($instance instanceof \melt\Model);
        \assert($style === null || \is_string($style));
        $ui_fields = $instance->uiGetInterface($this->interface_name, $field_set_name);
        if (!is_array($ui_fields))
            \trigger_error("No '" . $this->interface_name . "', '$field_set_name' interface returned by " . \get_class($instance) . ". (Undeclared?)", \E_USER_ERROR);
        return $this->detachInterfaceComponents($instance, \array_keys($ui_fields));
    }

    /**
     * Detaches the components for the specified interface field(s) and
     * returns their respective id.
     * Only detaches the given set of components related to the fields,
     * not the actual instance.
     * @see ModelInterface::detachInstance() to detach the actual instance.
     * @see ModelInterface::getInterfaceComponents() for more details.
     * @param \melt\Model $instance
     * @param mixed $fields
     * @return array
     */
    public function detachInterfaceComponents(\melt\Model $instance, $fields) {
        if (!\is_array($fields))
            $fields = array($fields);
        $instance_key = $this->getMemoryInstanceKey($instance);
        $instance_key_offset = $this->getInstanceKeyOffset($instance);
        $detached_components = array();
        foreach ($fields as $field_name) {
            if (\strlen($field_name) == 0 || $field_name[0] == "_")
                continue;
            if (!$instance->hasField($field_name))
                \trigger_error("Error in interface '" . $this->interface_name . "' for " . \get_class($instance) . ": '$field_name' is not a valid field/column name!", \E_USER_ERROR);
            if (substr($field_name, -3) == "_id")
                \trigger_error("Error in interface '" . $this->interface_name . "' for " . \get_class($instance) . ": Field syntax '$field_name' is reserved for id access. Pointer fields must be passed without '_id' suffix.", \E_USER_ERROR);
            // Generate the html key/id.
            $component_id = "qmiid" . \substr(\sha1($field_name . "," . $instance_key_offset . "," . $this->interface_name), 0, 12);
            // Unregister the component.
            if (isset($this->components[$component_id])) {
                $detached_components[$component_id] = $component_id;
                unset($this->components[$component_id]);
            }
        }
        // Return the component ids that was detached.
        return $detached_components;
    }

    /**
     * @internal
     */
    public function __jsMutate($operations) {
        $responses = array();
        foreach ($operations as $operation) {
            list($function_name, $arguments, $jit_reference) = $operation;
            // Dereference/evaluate target instance.
            $instance_declaration = $arguments[0];
            if ($instance_declaration === self::INSTANCE_JIT_REFERENCE) {
                // Referencing an instance just-in-time.
                if (!isset($this->jit_references[$jit_reference]))
                    \melt\request\show_invalid();
                $instance = $this->instances[$this->jit_references[$jit_reference]];
            } else {
                // Load the related instance.
                list($auto_jit_declared, $instance_ref) = $instance_declaration;
                if ($auto_jit_declared) {
                    if (!isset($this->jit_references[$instance_ref]))
                        \melt\request\show_404();
                    $instance = $this->instances[$this->jit_references[$instance_ref]];
                } else {
                    $instance = self::getReferencedInstance($instance_ref);
                    if (!$instance instanceof \melt\Model)
                        \melt\request\show_404();
                    if (!$instance->isLinked()) {
                        // Adding an instance dynamically, add JIT reference to it.
                        $jit_reference = \melt\string\random_alphanum_str(10);
                        $this->jit_references[$jit_reference] = $this->getMemoryInstanceKey($instance);
                        \header("X-Qmi-Instance-Id: $jit_reference");
                    }
                }
            }
            $arguments[0] = $instance;
            // Execute mutation function.
            $responses[] = \call_user_func_array(array($this, $function_name), $arguments);
        }
        $response = \json_encode(\count($responses) > 1? $responses: \reset($responses));
        $qmi_blob = $this->serialize();
        \melt\request\reset();
        \header("X-Qmi-Blob-Length: " . \strlen($qmi_blob));
        \header("Content-Type: application/octet-stream");
        print $qmi_blob;
        print $response;
        exit;
    }

    private function getJsExpression($function_name, array $arguments) {
        \assert($arguments[0] instanceof \melt\Model || $arguments[0] === self::INSTANCE_JIT_REFERENCE);
        if ($arguments[0] instanceof \melt\Model) {
            $instance_id = \spl_object_hash($arguments[0]);
            if (\array_key_exists($instance_id, $this->instances) && !$arguments[0]->isLinked()) {
                // If already attached but not linked we genereate an automatic JIT reference to the instance.
                $this->attachChanges($arguments[0]);
                $instance_key = $this->getMemoryInstanceKey($arguments[0]);
                $jit_reference = null;
                foreach ($this->jit_references as $jit_reference_2 => $instance_key_2) {
                    if ($instance_key_2 === $instance_key) {
                        $jit_reference = $jit_reference_2;
                        break;
                    }
                }
                if ($jit_reference === null) {
                    $jit_reference = \melt\string\random_alphanum_str(10);
                    $this->jit_references[$jit_reference] = $instance_key;
                }
                $instance_declaration = array(true, $jit_reference);
            } else {
                // Just a normal instance reference.
                $instance_declaration = array(false, self::getInstanceReference($arguments[0], true));
            }
            $arguments[0] = $instance_declaration;
        }
        $operation = array(\substr($function_name, 2), $arguments);
        $operation_blob = \melt\string\simple_crypt(\gzcompress(\serialize($operation)));
        $identity = $this->identity;
        return "$identity,$operation_blob";
    }

    /**
     * @see ModelInterface::getInterface() for more details.
     * @return string Operation blob. Pass to qmi_mutate.
     */
    public function jsGetInterface($instance, $field_set_name = null, $style = null, array $additional_view_data = array()) {
        \assert($style === null || \is_string($style));
        return self::getJsExpression(__FUNCTION__, \func_get_args());
    }

    /**
     * @see ModelInterface::getInterfaceComponents() for more details.
     * @return string Operation blob. Pass to qmi_mutate.
     */
    public function jsGetInterfaceComponents($instance, $fields, $field_labels = array()) {
        return self::getJsExpression(__FUNCTION__, \func_get_args());
    }

    /**
     * @see ModelInterface::detachInstance() for more details.
     * @return string Operation blob. Pass to qmi_mutate.
     */
    public function jsDetachInstance($instance) {
        return self::getJsExpression(__FUNCTION__, \func_get_args());
    }

    /**
     * @see ModelInterface::detachInterface() for more details.
     * @return string Operation blob. Pass to qmi_mutate.
     */
    public function jsDetachInterface($instance, $field_set_name = null) {
        return self::getJsExpression(__FUNCTION__, \func_get_args());
    }

    /**
     * @see ModelInterface::detachInterfaceComponents() for more details.
     * @return string Operation blob. Pass to qmi_mutate.
     */
    public function jsDetachInterfaceComponents(\melt\Model $instance, $fields) {
        return self::getJsExpression(__FUNCTION__, \func_get_args());
    }

    public function serialize() {
        // Convert instance object references to id|class references.
        $instance_references = $this->instances;
        foreach ($instance_references as &$instance)
            $instance = self::getInstanceReference($instance, false);
        // Serialize to encrypted blob.
        return \melt\string\simple_crypt(\gzcompress(\serialize(array(
            $instance_references,
            $this->new_instance_tags,
            $this->setters,
            $this->components,
            $this->autolinks,
            $this->jit_references,
            $this->interface_name,       
            $this->success_url,
            $this->creating,
            $this->default_style,
            $this->time_created,
            $this->identity,
            $this->continuum_steps,
        ))));
    }

    public static function unserialize($qmi_data) {
        $qmi_data = \melt\string\simple_decrypt($qmi_data);
        if ($qmi_data === false)
            return false;
        $qmi_data = @\unserialize(\gzuncompress($qmi_data));
        if (!\is_array($qmi_data))
            return false;
        $model_interface = new ModelInterface(null);
        list(
            $instance_references,
            $model_interface->new_instance_tags,
            $setters,
            $model_interface->components,
            $model_interface->autolinks,
            $model_interface->jit_references,
            $model_interface->interface_name,
            $model_interface->success_url,
            $model_interface->creating,
            $model_interface->default_style,
            $model_interface->time_created,
            $model_interface->identity,
            $model_interface->continuum_steps
        ) = $qmi_data;
        // Restore all instances.
        $new_instance_keys = array();
        foreach ($instance_references as $old_instance_key => $instance_reference) {
            $instance = self::getReferencedInstance($instance_reference);
            if ($instance === false)
                return false;
            $new_instance_key = \spl_object_hash($instance);
            $model_interface->instances[$new_instance_key] = $instance;
            $new_instance_keys[$old_instance_key] = $new_instance_key;
        }
        // Translate all instance keys from old to new
        foreach ($model_interface->new_instance_tags as &$new_instance_tag)
            $new_instance_tag = $new_instance_keys[$new_instance_tag];
        foreach ($model_interface->components as &$component)
            $component[0] = $new_instance_keys[$component[0]];
        foreach ($model_interface->jit_references as &$instance_key)
            $instance_key = $new_instance_keys[$instance_key];
        foreach ($setters as $old_instance_key => $setter)
            $model_interface->setters[$new_instance_keys[$old_instance_key]] = $setter;
        foreach ($model_interface->autolinks as &$autolink) {
            $autolink[0] = $new_instance_keys[$autolink[0]];
            $autolink[1] = $new_instance_keys[$autolink[1]];
        }
        return $model_interface;
    }
    
    public static function _checkSubmit() {
        if (!\array_key_exists("_qmi", $_POST))
            return;
        $model_interface = self::unserialize($_POST["_qmi"]);
        if ($model_interface === false)
            \melt\messenger\redirect_message(REQ_URL, _("Saving changes failed."));
        $model_interface->processSubmit();
    }

    /**
     * Returns a simple array of component keys mapped to their current
     * instance value. The data structure is used when pre-filling form values
     * that has already been entered at a previous point in time.
     * @return string
     */
    public function getComponentFieldValues() {
        $component_field_values = array();
        foreach ($this->instances as $instance_key => $instance) {
            foreach ($instance as $field_name => $field_type) {
                if (!isset($this->components[$instance_key][$field_name]))
                    continue;
                $component_key = $this->components[$instance_key][$field_name];
                $value = ($field_type instanceof \melt\core\PointerType)? $field_type->getID(): $field_type->get();
                $component_field_values[$component_key] = $value;
            }
        }
        return $component_field_values;
    }

    /**
     * Returns true if has visited the continuum step.
     * @param string $interface_name
     * @return boolean
     */
    public function hasVisitedContinuum($interface_name) {
        return \array_key_exists($interface_name, $this->continuum_steps);
    }

    /**
     * Is used to generate an interface for continuous
     * interaction with a model interface state - such as in step to step
     * guides. It takes the name of the desired next ModelInterface and
     * returns a new object. If the step has previously not been visited
     * this is equivialent to generating a new model interface with
     * previous instances attached to it. If the step has already been visited
     * (if getInterfaceContinuum() has been called transisioning from or to it)
     * then internaly cached information of how the fields where filled
     * and what instances was stored before rendering the interface will be
     * used. This is similar to how qmi handles invalid non-ajax redirections
     * although the data is stored in the model interface rather than
     * temporarily in the session.
     * @param string $next_interface_name
     * @return ModelInterface
     */
    public function getInterfaceContinuum($next_interface_name) {
        $prev_interface_name = $this->interface_name;
        $new_instance_to_tag_map = \array_flip($this->new_instance_tags);
        $next_model_interface = new ModelInterface($next_interface_name, $this->default_style, $this->success_url);
        // Transfer all changes made in previous step and log being on step.
        foreach ($this->instances as $instance)
            $next_model_interface->attachChanges($instance);
        $next_model_interface->continuum_steps = $this->continuum_steps;
        $next_model_interface->continuum_steps[$prev_interface_name] = true;
        $next_model_interface->new_instance_tags = $this->new_instance_tags;
        return $next_model_interface;
    }
    
    /**
     * Handles post data returned from a generated interface.
     * @return array
     */
    public function processSubmit() {
        $instance_components = array();
        if (isset($_POST['_qmi_auto_delete_button'])) {
            $is_deleting = true;
        } else {
            $is_deleting = false;
            // Process all setters.
            foreach ($this->setters as $instance_key => $fields) {
                foreach ($fields as $field_name => $value)
                    $this->instances[$instance_key]->$field_name = $value;
            }
            // Connect the instances that should be connected.
            foreach ($this->autolinks as $autolink) {
                list($source_model_key, $target_model_key, $pointer_field_name) = $autolink;
                $pointer_model = $this->instances[$source_model_key];
                $target_model = $target_model_key !== null? $this->instances[$target_model_key]: null;
                $pointer_model->$pointer_field_name = $target_model;
            }
            // Read all components from post data (overwriting setters).
            foreach ($this->components as $component_id => $component) {
                list($instance_key, $field_name) = $component;
                $this->instances[$instance_key]->type($field_name)->readInterface($component_id);
                $instance_components[$instance_key][$field_name] = $component_id;
            }
        }
        // Extract callback from interface name.
        $pos = \strpos($this->interface_name, '\\');
        if ($pos !== false) {
            $callback_module = \substr($this->interface_name, 0, $pos);
            $callback_method = \substr($this->interface_name, $pos + 1);
        } else {
            $callback_module = 'qmi';
            $callback_method = $this->interface_name;
        }
        $callback_method = "ic_$callback_method";
        $callback_class = 'melt\\' . $callback_module . '\\InterfaceCallback';
        if (!\class_exists($callback_class))
            \trigger_error(__METHOD__ . " error: The callback class '$callback_class' does not exist!", \E_USER_ERROR);
                if (!is($callback_class, 'melt\qmi\InterfaceCallback'))
            \trigger_error(__METHOD__ . " error: The callback class '$callback_class' does not extend 'melt\qmi\InterfaceCallback'!", \E_USER_ERROR);
        if (!is($callback_class, $callback_class . "_app_overrideable"))
            \trigger_error(__METHOD__ . " error: The callback class '$callback_class' is not declared overridable by the responsible module!", \E_USER_ERROR);
        $ajax_submit = array_key_exists("_qmi_ajax_submit", $_POST) && $_POST["_qmi_ajax_submit"] == true;
        $model_interface = $this;
        $callback_class = new $callback_class($this->interface_name, $this->instances, $instance_components, $is_deleting, $this->success_url, $ajax_submit, $this->time_created, $this);
        $callback_class->$callback_method();
        if ($ajax_submit) {
            $data = array("success" => true, "unlinked" => false, "errors" => array());
            foreach ($this->instances as $instance) {
                if (!$instance->isLinked()) {
                    $data["unlinked"] = true;
                    break;
                }
            }
            \melt\request\send_json_data($data);
        } else
            \melt\request\redirect($callback_class->getSuccessUrl());
    }
}
