<?php

abstract class SingletonModel extends DataSet {
    public static final function getInstance() {
        static $instances = array();
        $name = get_called_class();
        if (isset($instances[$name]))
            return $instances[$name];
        $instance = $instances[$name] = new $name(0);
        $vars = get_class_vars($name);
        $keys = array();
        foreach ($vars as $key => $var) {
            if ($key[0] == "_")
                continue;
            $keys[] = strtolower($name . "." . $key);
        }
        if (count($keys) == 0)
            return $instance;
        $where = "k = \"" . implode("\" OR k = \"", $keys) . "\"";
        $table = _tblprefix . "singleton_memory";
        $result = api_database::query("SELECT k,v FROM $table WHERE $where");
        while (FALSE !== ($row = api_database::next_array($result))) {
            $key = preg_replace('#[^\.]*\.#', "", $row[0]);
            $value = $row[1];
            $instance->$key->set(unserialize($value));
        }
        return $instance;
    }

    public final function store() {
        $name = get_class($this);
        $vars = get_class_vars($name);
        $table = _tblprefix . "singleton_memory";
        foreach ($vars as $key => $default) {
            if ($key[0] == "_")
                continue;
            $value = $this->$key->get();
            $key = api_database::strfy(strtolower($name . "." . $key));
            $value = api_database::strfy(serialize($value));
            api_database::query("INSERT INTO $table (k,v) VALUES ($key,$value) ON DUPLICATE KEY UPDATE v=$value");
        }
    }

    public static final function syncWithDatabase() {
        // Just check if the table exists and is not obstructed.
        // Don't bother with any fancy column checking.
        $all_tables = api_database::get_all_tables();
        $table = _tblprefix . "singleton_memory";
        if (!in_array($table, $all_tables))
            api_database::query("CREATE TABLE `" . _tblprefix . "singleton_memory` (`k` varchar(64) NOT NULL, `v` text, PRIMARY KEY (`k`));");
    }


    protected final function getInterfaceDataSetAndAction($mif_name, $mif_id, $mif_redirect, $mif_delete_redirect) {
        $success_msg = __("Record was successfully updated.");
        $model = forward_static_call(array($mif_name, 'getInstance'));
        return array($model, $success_msg);
    }

}

?>
