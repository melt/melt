<?php

class Model {
    /** @desc ID column, all models have ID's. */
    private $_id;

    /**
    * @desc Validates the current data. If invalid, returns an array of all fields name => reason mapped,
    *       otherwise, returns an empty array.
    * @return Array All invalid fields, name => reason mapped.
    * @desc Designed to be overriden.
    */
    public function validate() {
        return array();
    }

    /**
    * @desc Make any additional calculations you need to do before this instance is truly stored to the database.
    * @desc Designed to be overriden.
    */
    public function beforeInsert() {
        return;
    }

    /**
    * @desc Make any additional calculations you need to do before this instance is updated in the database.
    *       NOT CALLED ON INITIAL STORAGE, ONLY CALLED WHEN UPDATING.
    * @desc Designed to be overriden.
    */
    public function beforeUpdate() {
        return;
    }


    /**
    * @desc Direct instancing is not availible anywhere else.
    * @desc The creator functions must be used.
    */
    private function __construct() { }

    /**
    * @desc Returns a list of the columns of the specified model.
    * @desc Note: Does not return the implicit ID column.
    * @return Array An array of the column names in the specified model.
    */
    public static function getColumns($name) {
        $columns = array();
        if (!class_exists($name) || !is_subclass_of($name, 'Model'))
            throw new Exception("'$name' is not a valid class and/or not a valid Model!");
        foreach (get_class_vars($name) as $colname => $def)
            if ($colname[0] != '_')
                $columns[] = $colname;
        return $columns;
    }

    /**
    * @desc Returns a list of the column types.
    * @desc Note: Does not return the implicit ID column.
    * @return Array An array of key values where keys are column names and values are their type handler instances.
    */
    public static function getColumnTypes($name) {
        static $col_cache = array();
        if (isset($col_cache[$name]))
            return $col_cache[$name];
        $vars = get_class_vars($name);
        // Remap to an array that contains non storing columns.
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
            // Soft crash if expected type does not exist.
            if (!class_exists($clsname)) {
                _nanomvc_autoload($clsname);
                if (!class_exists($clsname))
                    throw new Exception("Invalid type: No type handler class declared/found called '$clsname' for column '$colname'.");
            }
            $type_handler = new $clsname();
            if (count($attributes) > 1) {
                for ($i = 1; $i < count($attributes); $i++) {
                    $attribute = $attributes[$i];
                    $eqp = strpos($attribute, "=");
                    if ($eqp === false)
                        throw new Exception("Syntax Error: The attribute token '$eqp' lacks equal sign (=).");
                    $key = substr($attribute, 0, $eqp);
                    $val = substr($attribute, $eqp + 1);
                    $type_handler->$key = $val;
                }
            }
            $columns[$colname] = $type_handler;
        }
        // Cache and return.
        return $col_cache[$name] = $columns;
    }

    /**
    * @desc Returns the ID of this model instance or FALSE if not stored yet.
    */
    public function getID() {
        return ($this->_id < 1)? false: $this->_id;
    }

    /**
    * @desc Removes this model instance from the database.
    */
    public function remove() {
        $id = $this->_id;
        $this->_id = -1;
        return $this->removeByID($id);
    }

    private function getInsertSQL($name) {
        $columns = self::getColumnTypes($name);
        static $key_list_cache = array();
        if (!isset($key_list_cache[$name])) {
            $key_list = implode(',', array_keys($columns));
            $key_list_cache[$name] = $key_list;
        } else
            $key_list = $key_list_cache[$name];
        $value_list = array();
        foreach ($columns as $colname => $coltype)
            $value_list[] = $coltype->SQLize($this->$colname);
        $value_list = implode(',', $value_list);
        return "INSERT INTO `"._tblprefix."$name` ($key_list) VALUES ($value_list)";
    }

    private function getUpdateSQL($name) {
        $columns = self::getColumnTypes($name);
        $value_list = array();
        foreach ($columns as $colname => $coltype)
            $value_list[] = "`$colname`=" . $coltype->SQLize($this->$colname);
        $value_list = implode(',', $value_list);
        $id = intval($this->_id);
        return "UPDATE `"._tblprefix."$name` SET $value_list WHERE id=$id";
    }

    /**
    * @desc Stores any changes to this model instance to the database.
    * @desc If this is a new instance, it's inserted, otherwise, it's updated.
    */
    public function store() {
        $name = get_class($this);
        $columns = self::getColumnTypes($name);
        // Generate query.
        $value_list = implode(',', $columns);
        $update_ex = null;
        if ($this->_id > 0) {
            // Updating existing row.
            $this->beforeUpdate();
            api_database::query($this->getUpdateSQL($name));
        } else {
            // Inserting.
            $this->beforeInsert();
            api_database::query($this->getInsertSQL($name));
            $this->_id = api_database::insert_id();
        }
    }

    /**
    * @desc Attempts to syncronize the layout of this model against the database table layout.
    */
    public static function syncLayout() {
        $name = get_called_class();
        $columns = self::getColumnTypes($name, true);
        api_database::sync_table_layout($name, $columns);
    }

    /**
    * @desc Creates a new model instance.
    * @returns Model A new model instance.
    */
    public static final function insertNew() {
        $name = get_called_class();
        $model = new $name();
        $columns = self::getColumnTypes($name);
        foreach ($columns as $colname => $coltype)
            $model->$colname = null;
        $model->id = -1;
        return $model;
    }

    /**
    * @desc This function sets this model instance to the stored ID specified.
    * @return Model The model with the ID specified or FALSE.
    */
    public static final function selectByID($id) {
        $name = get_called_class();
        $model = new $name();
        $res = api_database::query("SELECT * FROM `"._tblprefix."$name` WHERE id = ".intval($id));
        if (api_database::get_num_rows($res) != 1)
            return false;
        $sql_row = api_database::next_assoc($res);
        $columns = self::getColumnTypes($name, true);
        foreach ($columns as $colname => $coltype)
            $model->$colname = $sql_row[$colname];
        $model->_id = $sql_row['id'];
        return $model;
    }


    private static final function makeArrayOf($name, $sql_result) {
        $length = api_database::get_num_rows($sql_result);
        if ($length == 0)
            return array();
        $array = array();
        $columns = self::getColumnTypes($name, true);
        for ($at = 0; $at < $length; $at++) {
            $row = api_database::next_assoc($sql_result);
            $on = new $name();
            $array[] = $on;
            foreach ($columns as $colname => $coltype)
                $on->$colname = $row[$colname];
            $on->_id = $row['id'];
        }
        return $array;
    }

    /**
    * @desc This function selects the first model instance that matches the specified $where clause.
    * @desc If there are no match, it returns FALSE.
    * @desc For security reasons, use api_database::strfy() to escape and quote
    * @desc any strings you want to build your sql query from.
    * @param String $where (WHERE xyz) If specified, any ammount of conditionals to filter out the row.
    * @desc Model The model instance that matches or FALSE if there are no match.
    */
    public static final function selectFirst($where) {
        $match = self::selectWhere($where, 0, 1);
        return (count($match) == 0)? FALSE: $match[0];
    }

    /**
    * @desc This function returns an array of model instances that is selected by the given SQL arguments.
    * @desc For security reasons, use api_database::strfy() to escape and quote
    * @desc any strings you want to build your sql query from.
    * @param String $where (WHERE xyz) If specified, any number of where conditionals to filter out rows.
    * @param Integer $offset (OFFSET xyz) The offset from the begining to select results from.
    * @param Integer $limit (LIMIT offset,xyz) If you want to limit the number of results, specify this.
    * @param String $order (ORDER BY xyz) Specify this to get the results in a certain order, like 'description ASC'.
    * @desc Array An array of the selected model instances.
    */
    public static final function selectWhere($where = "", $offset = 0, $limit = 0, $order = "") {
        $name = get_called_class();
        $offset = intval($offset);
        $limit = intval($limit);
        if ($where != "")
            $where = " WHERE ".$where;
        if ($limit != 0)
            $limit = " LIMIT ".$offset.",".$limit;
        else if ($offset != 0)
            $limit = " OFFSET ".$offset;
        else $limit = "";
        if ($order != "")
            $order = " ORDER BY ".$order;
        $sql_result = api_database::query("SELECT * FROM `"._tblprefix."$name`".$where.$order.$limit);
        return self::makeArrayOf($name, $sql_result);
    }

    /**
    * @desc This function returns an array of model instances that matches the given SQL commands.
    * @desc For security reasons, use api_database::strfy() to escape and quote
    * @desc any strings you want to build your sql query from.
    * @param String $sqldata SQL command(s) that will be appended after the SELECT query for free selection.
    * @desc Array An array of the selected model instances.
    */
    public static final function selectFreely($sqldata) {
        $name = get_called_class();
        $sql_result = api_database::query("SELECT * FROM `"._tblprefix."$name` ".$sqldata);
        return self::makeArrayOf($name, $sql_result);
    }

    /**
    * @desc This function removes the model instance with the given ID.
    */
    public static final function removeByID($id) {
        $name = get_called_class();
        return api_database::query("DELETE FROM `"._tblprefix."$name` WHERE id = ".$id);
    }
    /**
    * @desc This function removes the selection of fields that matches the given SQL commands.
    * @desc For security reasons, use api_database::strfy() to escape and quote
    * @desc any strings you want to build your SQL query from.
    * @param String $sqldata SQL command(s) that will be appended after the DELETE query for free selection.
    */
    public static final function removeFreely($sqldata) {
        $name = get_called_class();
        return api_database::query("DELETE FROM `"._tblprefix."$name`".$sqldata);
    }

    /**
    * @desc This function removes the selection of fields that matches the given SQL arguments.
    * @desc For security reasons, use api_database::strfy() to escape and quote
    * @desc any strings you want to build your sql query from.
    * @param String $where (WHERE xyz) If specified, any number of where conditionals to match rows to delete.
    */
    public static final function removeWhere($where = "") {
        $name = get_called_class();
        if ($where != "")
            $where = " WHERE ".$where;
        return api_database::query("DELETE FROM `"._tblprefix."$name`".$where);
    }

    /**
    * @desc This function counts the number of model instances that matches given SQL arguments.
    * @desc For security reasons, use api_database::strfy() to escape and quote
    * @desc any strings you want to build your sql query from.
    * @param String $where (WHERE xyz) If specified, any number of where conditionals to filter out rows.
    * @param Integer $offset (OFFSET xyz) The offset from the begining to select results from.
    * @return Integer Number of matched rows.
    */
    public static final function count($where = "", $offset = 0) {
        $name = get_called_class();
        $offset = intval($offset);
        if ($where != "")
            $where = " WHERE ".$where;
        $result = api_database::query("SELECT COUNT(*) FROM `"._tblprefix."$name`".$where);
        $rows = api_database::next_array($result);
        return intval($rows[0]);
    }

    /**
    * @desc This function enlists a number of items. Useful when listing in views.
    * @desc For security reasons, use api_database::strfy() to escape and quote
    * @desc any strings you want to build your sql query from.
    * @param String $where (WHERE xyz) If specified, any number of where conditionals to filter out rows.
    * @param Integer $items_per_page The number of items per page.
    * @param String $page_specifyer The name of the GET variable that specifies the page we´re currently on.
    * @param String $order_column_specifyer The name of the GET variable that specifies the column used for ordering.
    * @param String $order_specifyer The name of the GET variable that specifies the order used in ordering.
    * @param Array $limit_ordering_to If you want to limit the possible column names that can be ordered, specify them here.
    * @return Array All items listed on the current page.
    */
    public static final function enlist($where = "", $items_per_page = 30, $page_specifyer = 'page', $order_column_specifyer = null, $order_specifyer = null, $limit_ordering_to = null) {
        $name = get_called_class();
        $total_count = forward_static_call(array($name, 'count'), $where);
        $page_ub = ceil($total_count / $items_per_page) - 1;
        if ($page_ub < 0)
            $page_ub = 0;
        $page = intval(@$_GET[$page_specifyer]);
        if ($page > $page_ub)
            $page = $page_ub;
        else if ($page < 0)
            $page = 0;
        $order = null;
        if ($order_column_specifyer != null) {
            $order_column = strval($_GET[$order_column_specifyer]);
            $columns = self::getColumns($name);
            // A correct ordering column?
            if (($limit_ordering_to === null || in_array($order_column, $order_column_specifyer))
            && in_array($order_column, $columns)) {
                $order = strval($_GET[$order_specifyer]);
                $decending = (strtolower($order) == "desc");
                $order = $order_column . ($decending? " DESC": " ASC");
            }
        }
        $items = forward_static_call(array($name, 'selectWhere'), $where, $page * $items_per_page, $items_per_page, $order);
        self::$_last_enlist_page_ub = $page_ub;
        self::$_last_enlist_page_current = $page;
        return $items;
    }

    private static $_last_enlist_page_current;
    private static $_last_enlist_page_ub;

    /**
    * @desc Returns an simple but useful navigation array for the last enlistment.
    *       The navigation array consists of pagenumbers and triple dots that indicate number jumps.
    */
    public static final function enlist_navigation() {
        // TODO: Write navigatior.
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
        $columns = self::getColumnTypes($name);
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
                // Insert or update?
                if ($mif_id > 0) {
                    $model = forward_static_call(array($mif_name, 'selectByID'), $mif_id);
                    $success_msg = __("Record was successfully updated.");
                } else {
                    $model = forward_static_call(array($mif_name, 'insertNew'));
                    $success_msg = __("Record was successfully added.");
                }
                // Delete?
                if (isset($_POST['do_delete'])) {
                    if ($model === false)
                        return;
                    if (!is_string($mif_delete_redirect) || strlen($mif_delete_redirect) == 0)
                        Flash::doFlash(__("You don't have permissions to delete this record."), FLASH_BAD);
                    else if ($mif_id <= 0)
                        Flash::doFlash(__("Conflicting operation: Delete and Insert. Ignored query."), FLASH_BAD);
                    else {
                        $model->remove();
                        Flash::doFlashRedirect($mif_delete_redirect, __("Record removed as requested."), FLASH_GOOD);
                    }
                    return;
                }
                if ($model === false) {
                    Flash::doFlash(__("The record no longer exists!"), FLASH_BAD);
                    return;
                }
                // Loop trough and read all specified data.
                $col_types = self::getColumnTypes($mif_name, true);
                foreach ($col_types as $name => $type)
                    if (in_array($name, $mif_fields))
                        $col_types[$name]->read($name, $model->$name);
                    else if (isset($mif_defaults[$name]))
                        $model->$name = $mif_defaults[$name];
                // Validate all data.
                $invalid_fields = $model->validate();
                if (count($invalid_fields) > 0) {
                    $invalid_data = array();
                    foreach ($mif_fields as $name)
                        $invalid_data[$name] = $model->$name;
                    self::$_invalid_mif_data = $invalid_data;
                    self::$_invalid_mif_name = $mif_name;
                    self::$_invalid_mif_fields = $invalid_fields;
                    Flash::doFlash(__("The requested operation failed. One or more fields where invalid."), FLASH_BAD);
                    return;
                }
                // Store changes.
                $model->store();
                // Redirect if it should do so.
                $redirect = (is_string($mif_redirect) && strlen($mif_redirect) > 0)? $mif_redirect: api_navigation::make_local_url(REQURL);
                Flash::doFlashRedirect($redirect, $success_msg, FLASH_GOOD);
                return;
            }
        }
        Controller::flash(__("The requested action failed, possibly due to timed out session."), FLASH_BAD);
    }


    /**
    * @desc Writes this model interface to an array by calling write on each type.
    */
    public final function write() {
        $name = get_class($this);
        $out = array();
        $columns = self::getColumnTypes($name);
        foreach ($columns as $name => $type)
            $out[$name] = $type->write($this->$name);
        $out['id'] = $this->getID();
        return $out;
    }
}

?>
