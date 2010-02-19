<?php

/**
* @desc A set of data. To implement this, you must somehow store data sets.
*/
abstract class DataSet {
    /** @desc Identifier of this data set, only readable. */
    protected $_id;

    /**
    * @desc Returns the ID of this model instance or FALSE if not stored yet.
    */
    public final function getID() {
        return ($this->_id < 1)? false: $this->_id;
    }

    /**
    * @desc Stores this Data Set.
    */
    abstract public function store();

    /**
    * @desc Verify that the specified classname exists and extends the parent.
    */
    private static function existsAndExtends($class_name, $parent) {
        if (!class_exists($class_name)) {
            _nanomvc_autoload($class_name);
            if (!class_exists($class_name))
                throw new Exception("The class '$class_name' is unknown or does not exist!");
        }
        if (!is_subclass_of($class_name, $parent))
            throw new Exception("The class '$class_name' was expected to extend the '$parent' class but didn't!");
    }

    /**
    * @desc Returns the pointer model name from the column name or NULL if column is not of pointer type.
    */
    protected static function getPointerModelName($colname) {
        $colname_parts = array_reverse(explode("_", $colname));
        $single_ptr = $colname_parts[0] == "id";
        $multi_ptr = !$single_ptr && $colname_parts[1] == "id";
        if ($single_ptr || $multi_ptr) {
            // Evaluate the pointer model name.
            unset($colname_parts[0]);
            if ($multi_ptr)
                unset($colname_parts[1]);
            return implode("_", array_reverse($colname_parts));
        } else
            return null;
    }

    /**
    * @desc Translates the field specifiers to type handler instances.
    */
    protected final function __construct($id) {
        $name = get_class($this);
        static $parsed_model_cache = array();
        if (!isset($parsed_model_cache[$name])) {
            $parsed_model = array();
            $vars = get_class_vars($name);
            $columns = array();
            foreach ($vars as $colname => $coltype) {
                // Ignore non column members.
                if ($colname[0] == '_')
                    continue;
                // Parse attributes.
                $attributes = explode(",", $coltype);
                $clsname = $attributes[0];
                if ($clsname == "")
                    throw new Exception("Invalid type: Column '$colname' has nothing specified in type field.");
                $clsname .= "Type";
                $ptr_model_name = self::getPointerModelName($colname);
                if ($ptr_model_name !== null) {
                    // Expects the type handler of this class to extend the special reference type.
                    self::existsAndExtends($ptr_model_name, "Model");
                    self::existsAndExtends($clsname, "Reference");
                    $type_handler = new $clsname($colname, null, $ptr_model_name);
                } else {
                    // Standard type handles must extend the type class.
                    self::existsAndExtends($clsname, "Type");
                    if (is_subclass_of($clsname, "Reference"))
                        throw new Exception("Invalid type: Column '$colname' has a Reference Type, without beeing a reference.");
                    $type_handler = new $clsname($colname, null);
                }
                if (count($attributes) > 1) {
                    for ($i = 1; $i < count($attributes); $i++) {
                        $attribute = $attributes[$i];
                        $eqp = strpos($attribute, "=");
                        if ($eqp === false)
                            throw new Exception("Syntax Error: The attribute token '$eqp' lacks equal sign (=).");
                        $key = substr($attribute, 0, $eqp);
                        $val = substr($attribute, $eqp + 1);
                        if (!isset($type_handler->$key))
                            throw new Exception("Error in $name.$colname attribute list: The type '$clsname' does not have an attribute named '$key'.");
                        $type_handler->$key = $val;
                    }
                }
                // Cache this untouched type instance and clone it to other new instances.
                $parsed_model[$colname] = $type_handler;
            }
            $parsed_model_cache[$name] = $parsed_model;
        } else
            $parsed_model = $parsed_model_cache[$name];
        foreach ($parsed_model as $colname => $type_instance)
            $this->$colname = clone $type_instance;
        $this->_id = intval($id);
    }

    /**
    * @desc Returns a list of the column names of the specified model.
    * @desc Note: Does not return the implicit ID column.
    * @return Array An array of the column names in the specified model.
    */
    public static final function getColumnNames($name) {
        static $columns_name_cache = array();
        if (isset($columns_name_cache[$name]))
            return $columns_name_cache[$name];
        $columns = array();
        if (!class_exists($name) || !is_subclass_of($name, 'DataSet'))
            throw new Exception("'$name' is not a valid class and/or not a valid DataSet!");
        foreach (get_class_vars($name) as $colname => $def)
            if ($colname[0] != '_')
                $columns[] = $colname;
        return $columns_name_cache[$name] = $columns;
    }

    private $_columns_cache = null;

    /**
    * @desc Returns a list of the columns in this model for dynamic iteration.
    * @return Array An array of the columns in this model.
    */
    public final function getColumns() {
        if ($this->_columns_cache !== null)
            return $this->_columns_cache;
        $vars = get_object_vars($this);
        $columns = array();
        foreach ($vars as $colname => $value) {
            if ($colname[0] != '_') {
                $columns[$colname] = $value;
                if (!is_object($value))
                    throw new Exception("The column '$colname' is corrupt. Expected object, found: " . var_export($value, true) . ".");
            }
        }
        return $this->_columns_cache = $columns;
    }


    /**
    * @desc Returns an interface of HTML components that lets the user operate on this model instance.
    * @param Array $fields            Array of all fields (fieldnames) that will be included in the operation,
    *                                 if null, all fields will be included.
    *
    * @param Array $labels            Array of field names mapped to the labels they will have in the output interface.
    *
    * @param Array $defaults          Array of field names mapped to the default value they will be set to trough this interface.
    *                                 Theese values are client side immutable.
    *
    * @param String $redirect         Local url to redirect to if the interface query was successful.
    *                                 Set to null to not redirect.
    *
    * @param Boolean $delete_redirect Set to an url to redirect after deletion to allow the user to delete this model.
    *                                 Trigger deleting by submitting with the name "do_delete".
    *                                 (Only used in standard Models - SingletonModel does not suport delete.)
    *
    * @return Array Array of all HTML components that makes up this interface instance.
    */
    public final function getInterface($fields = null, $labels = null, $defaults = null, $redirect = null, $delete_redirect = null) {
        $name = get_class($this);
        // Set the model interface validation/encryption key if this has not been set.
        if (!isset($_SESSION['mif_val_key']))
            $_SESSION['mif_val_key'] = api_string::gen_key();
        if (!isset($_SESSION['mif_enc_key']))
            $_SESSION['mif_enc_key'] = api_string::gen_key();
        $mif_val_key = $_SESSION['mif_val_key'];
        $mif_enc_key = $_SESSION['mif_enc_key'];
        // Refill fields on invalid-callback.
        $invalid_callback = $name == self::$_invalid_mif_name;
        if ($invalid_callback)
            foreach (self::$_invalid_mif_data as $col_name => $data)
                $this->$col_name = $data;
        // Get columns.
        $columns = $this->getColumns();
        $fields_found = array();
        // Create user interfaces.
        $fields = (is_array($fields)? $fields: array());
        foreach ($fields as $col_name) {
            if (!array_key_exists($col_name, $columns))
                continue;
            // Pass this field to the user trough an interface.
            $fields_found[] = $col_name;
            $label = isset($labels[$col_name])? $labels[$col_name]: $col_name;
            $value = isset($defaults[$col_name])? $defaults[$col_name]: $this->$col_name;
            $interface = $columns[$col_name]->getInterface($label, $value, $col_name);
            if (!is_string($interface))
                continue;
            $out_fields[$col_name] = "<div class=\"mif_component\">$interface</div>";
        }
        // Make sure all requested fields existed.
        $fields_missing = array_diff($fields, $fields_found);
        if (count($fields_missing) > 0)
            throw new Exception("The following fields does not exist: " . implode(", ", $fields_missing));
        // Append static model interface data in special static data field, and secure it.
        $mif_header = array(
            $mif_val_key,
            $name,
            $this->_id,
            $fields,
            $defaults,
            $redirect,
            $delete_redirect
        );
        $mif_header = api_string::simple_crypt(serialize($mif_header), $mif_enc_key);
        $out_fields['_mif'] = "<input type=\"hidden\" name=\"_mif_header\" value=\"$mif_header\" />";
        if ($invalid_callback) {
            // Append invalidation info.
            foreach ($out_fields as $col_name => &$if) {
                if (isset(self::$_invalid_mif_fields[$col_name])) {
                    $invalid_info = self::$_invalid_mif_fields[$col_name];
                    $if .= " <div class=\"mif_invalid\">$invalid_info</div>";
                }
            }
        }
        return $out_fields;
    }

    // Passing theese parameters upwards from processInterfaceAction()
    // to getInterface() in case form input was invalid and user must be notified about input mistakes.
    private static $_invalid_mif_fields;
    private static $_invalid_mif_name;
    private static $_invalid_mif_data;

    /**
    * @desc Process action queried by interface.
    */
    public static final function processInterfaceAction() {
        // Make sure scaffold key is set.
        if (isset($_SESSION['mif_val_key']) && isset($_SESSION['mif_enc_key'])) {
            $mif_val_key = $_SESSION['mif_val_key'];
            $mif_enc_key = $_SESSION['mif_enc_key'];
            // Return and decrypt the mif header.
            $mif_header = api_string::simple_decrypt($_POST['_mif_header'], $mif_enc_key);
            if (is_string($mif_header)) {
                $mif_header = unserialize($mif_header);
                if ($mif_header === false || !is_array($mif_header))
                    throw new Exception("The MIF header was corrupt! Follows: >>>" . var_export($mif_header, true) . "<<<");
                list($mif_val_key_hdr, $mif_name, $mif_id, $mif_fields, $mif_defaults, $mif_redirect, $mif_delete_redirect) = $mif_header;
                if ($mif_val_key_hdr !== $mif_val_key)
                    throw new Exception("The MIF validation key was corrupt! ($mif_val_key_hdr !== $mif_val_key)");
                $interfaceDataSetAndAction = forward_static_call(array($mif_name, 'getInterfaceDataSetAndAction'), $mif_name, $mif_id, $mif_redirect, $mif_delete_redirect);
                if ($interfaceDataSetAndAction == null)
                    return;
                list($data_set, $success_msg) = $interfaceDataSetAndAction;
                // Loop trough and read all specified data.
                foreach ($data_set->getColumns() as $name => $column)
                    if (in_array($name, $mif_fields))
                        $column->readInterface();
                    else if (isset($mif_defaults[$name]))
                        $column->set($mif_defaults[$name]);
                // Validate all data.
                $invalid_fields = $data_set->validate();
                if (count($invalid_fields) > 0) {
                    $invalid_data = array();
                    foreach ($mif_fields as $name)
                        $invalid_data[$name] = $data_set->$name;
                    self::$_invalid_mif_data = $invalid_data;
                    self::$_invalid_mif_name = $mif_name;
                    self::$_invalid_mif_fields = $invalid_fields;
                    Flash::doFlash(__("The requested operation failed. One or more fields where invalid."), FLASH_BAD);
                    return;
                }
                // Store changes.
                $data_set->store();
                // Redirect if it should do so.
                if (is_string($mif_redirect) && strlen($mif_redirect) > 0)
                    $redirect = str_replace("{id}", $data_set->getID(), $mif_redirect);
                else
                    $redirect = api_navigation::make_local_url(REQURL);
                Flash::doFlashRedirect($redirect, $success_msg, FLASH_GOOD);
                exit;
            }
        }
        Flash::doFlash(__("The requested action failed, possibly due to timed out session."), FLASH_BAD);
    }

    // This function have to use the storage implementation, and since DataSet leaves that undefined,
    // childs are required to override this function.
    protected abstract function getInterfaceDataSetAndAction($mif_name, $mif_id, $mif_redirect, $mif_delete_redirect);


    /**
    * @desc Returns this data set as an array with values html/string represented.
    */
    public final function write() {
        $out = array();
        $columns = $this->getColumns();
        foreach ($columns as $name => $type)
            $out[$name] = (string) $type;
        $out['id'] = $this->getID();
        return $out;
    }


    /**
    * @desc Validates the current data. If invalid, returns an array of all fields name => reason mapped,
    *       otherwise, returns an empty array.
    * @return Array All invalid fields, name => reason mapped.
    * @desc Designed to be overriden.
    */
    public function validate() {
        return array();
    }
}

?>