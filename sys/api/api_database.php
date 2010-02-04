<?php

/**
*@desc The database api namespace.
*/
class api_database {
    const DRIVER_MYSQL = 0;
    const DRIVER_MSSQL = 1;

    private static $initialized = false;
    private static $display = false;

    /**
    * @desc Calling this function enables buffer printing of all SQL queries.
    *       Useful for SQL batch scripts.
    */
    public static function enable_display() {
        api_misc::ob_reset();
        header('Content-Type: text/plain');
        api_database::$display = true;
    }

    public static function get_auto_increment($table) {
        switch (_vcms_dbdriver) {
            case api_database::DRIVER_MYSQL:
                $r = api_database::query("SELECT AUTO_INCREMENT FROM information_schema.tables WHERE table_name='".$table."'");
                $r = api_database::next_array($r);
                return $r[0];
            case api_database::DRIVER_MSSQL:
                $r = api_database::query("SELECT IDENT_CURRENT ('".$table.".id') AS Current_Identity;");
                $r = api_database::next_array($r);
                return $r[0];
        }
    }

    public static function set_auto_increment($table, $id, $errmsg = null) {
        switch (_vcms_dbdriver) {
            case api_database::DRIVER_MYSQL:
                api_database::query("ALTER TABLE `" . $table . "` AUTO_INCREMENT = ".intval($id), $errmsg);
                return;
            case api_database::DRIVER_MSSQL:
                api_database::query("ALTER TABLE " . $table .
                               "MODIFY (id INT IDENTITY(".intval($id).",1) not null)", $errmsg);
                return;
        }
    }

    /**
    * @desc Get the ID generated from the previous INSERT operation
    * @return The ID generated for an AUTO_INCREMENT column by the previous INSERT query on success, 0 if the previous query does not generate an AUTO_INCREMENT value, or FALSE if no MySQL connection was established.
    */
    public static function insert_id() {
        /*
        switch (_vcms_dbdriver) {
            case DBX_MYSQL:
                return mysql_insert_id(_vcms_dbhandle);
            case DBX_MSSQL:
                $q = mssql_query("SELECT LAST_INSERT_ID=@@IDENTITY", _vcms_dbhandle);
                $r = mssql_fetch_assoc($q);
                return intval($r['LAST_INSERT_ID']);
            case DBX_ODBC:
                trigger_error("insert_id() called with ODBC driver, current implementation is NOT stable!", E_WARNING);
                $q = odbc_exec(_vcms_dbhandle, "SELECT max(id) FROM table;");
                $r = mssql_fetch_assoc($q);
                return intval($r['max(id)']);
            case DBX_PGSQL:
                return pg_last_oid(_vcms_dbhandle);
            case DBX_FBSQL:
                return fbsql_insert_id(_vcms_dbhandle);
            case DBX_SYBASECT:
                $q = sybase_query("SELECT @@IDENTITY", _vcms_dbhandle);
                $r = mssql_fetch_assoc($q);
                return intval($r['max(id)']);
            case DBX_OCI8:

            case DBX_SQLITE:
        }*/
        /*$r = dbx_fetch_row(dbx_query(_vcms_dbhandle, "SELECT @@IDENTITY;"));
        return $r['@@IDENTITY'];*/
        $r = api_database::query("SELECT @@IDENTITY;");
        $r = api_database::next_array($r);
        return $r[0];
    }

    /**
    * @desc This function properly escapes and quotes any string you insert,
    * @desc making it ready to be directly inserted into your SQL queries.
    * @example Input: > a 'test' <   Output: > "a \'test\'" <
    * @return String The escaped and quoted string you inputed.
    */
    public static function strfy($string, $max_length = -1) {
        // Using addslashes() instead of "real escaping" as
        // local string processing should have much larger performance.
        if ($max_length >= 0)
            $string = substr($string, 0, $max_length);
        return '"' . addslashes($string) . '"';

    }

    /**
    * @desc Queries the database, and throws specified error on failure.
    * @desc It will throw an exception if query fails.
    * @param String $query The SQL query.
    * @param String $errmsg Additional information about the action that will be thrown if query fails.
    * @see To query without errorhandling, use api_database::run().
    * @return mixed Returns a result resource handle on success, TRUE if no rows were returned, or FALSE on error.
    */
    public static function query($query, $errmsg = "") {
        $result = api_database::run($query);
        if ($result === FALSE) {
            switch (_vcms_dbdriver) {
                case api_database::DRIVER_MYSQL:
                    $err = mysql_error(_vcms_dbhandle);
                    break;
                case api_database::DRIVER_MSSQL:
                    $err = mssql_get_last_message();
                    break;
            }
            if ($errmsg == "")
                $errmsg = "A SQL query { '".$query."' } to the database failed;\nSQL error: ".$err;
            else
                $errmsg = "A SQL query { '".$query."' } to the database failed;\nOperation information:\n".$errmsg."\nSQL error: ".$err;
            if (!api_database::$display)
                throw new Exception($errmsg);
            else
                echo $errmsg;
            exit;
        }
        return $result;
    }
    /**
    * @desc Runs a query on the database, ignoring any failure.
    * @param String $query The SQL query to execute on acms DB connection.
    * @see To query with errorhandling, use api_database::query().
    * @return mixed A result resource handle on success, TRUE if no rows were returned, or FALSE on error.
    */
    public static function run($query) {
        if (api_database::$initialized == false)
            api_database::init();
        if (api_database::$display) {
            echo $query . "\r\n";
            ob_flush();
        }
        switch (_vcms_dbdriver) {
            case api_database::DRIVER_MYSQL:
                return mysql_query($query, _vcms_dbhandle);
            case api_database::DRIVER_MSSQL:
                return mssql_query($query, _vcms_dbhandle);
        }
    }

    /**
    * @desc Returns the number of affected rows in the last query.
    */
    public static function affected_rows() {
        switch (_vcms_dbdriver) {
            case api_database::DRIVER_MYSQL:
                return mysql_affected_rows(_vcms_dbhandle);
            case api_database::DRIVER_MSSQL:
                return mssql_affected_rows(_vcms_dbhandle);
        }
    }

    /**
    * @return Number of rows in result.
    */
    public static function get_num_rows($result) {
        switch (_vcms_dbdriver) {
            case api_database::DRIVER_MYSQL:
                return mysql_numrows($result);
            case api_database::DRIVER_MSSQL:
                return mssql_num_rows($result);
        }
    }

    /**
    * @return Number of columns in result.
    */
    public static function get_num_cols($result) {
        switch (_vcms_dbdriver) {
            case api_database::DRIVER_MYSQL:
                return mysql_numfields($result);
            case api_database::DRIVER_MSSQL:
                return mssql_num_fields($result);
        }
    }

    /**
    * @return The next row in result as an associative array, or FALSE if there are no more rows.
    */
    public static function next_assoc($result) {
        switch (_vcms_dbdriver) {
            case api_database::DRIVER_MYSQL:
                return mysql_fetch_assoc($result);
            case api_database::DRIVER_MSSQL:
                return mssql_fetch_assoc($result);
        }
    }

    /**
    * @desc Seeks the result position to row n.
    */
    public static function data_seek($result, $n) {
        switch (_vcms_dbdriver) {
            case api_database::DRIVER_MYSQL:
                return mysql_data_seek($result, $n);
            case api_database::DRIVER_MSSQL:
                return mssql_data_seek($result, $n);
        }
    }

    /**
    * @return The next row in result as a numeric array, or FALSE if there are no more rows.
    */
    public static function next_array($result) {
        switch (_vcms_dbdriver) {
            case api_database::DRIVER_MYSQL:
                return mysql_fetch_array($result);
            case api_database::DRIVER_MSSQL:
                return mysql_fetch_array($result);
        }
    }

    /**
    * @desc Returns false if the current column is a subset of the specified column.
    * If current is int(9) or int(123) and specified is int, this returns false.
    * However, if current is int(23) and specified is int(12), this returns true.
    */
    private static function sql_column_need_update($specified, $current) {
        $lengthy_pattern = '#(\w+)\s*\((\d+)\)#';
        $specified_is_lengthy = (1 == preg_match($lengthy_pattern, $specified, $lengthy_specified));
        $current_is_lengthy = (1 == preg_match($lengthy_pattern, $current, $lengthy_current));
        if (!$current_is_lengthy && !$specified_is_lengthy) {
            // Needs update if they differ.
            return strtolower($specified) != strtolower($current);
        } else if ($current_is_lengthy && !$specified_is_lengthy) {
            // Needs update if type differ.
            $current_type = strtolower($lengthy_current[1]);
            return $current_type != strtolower($specified);
        } else if (!$current_is_lengthy && $specified_is_lengthy) {
            // Needs update (specified has length).
            return true;
        } else {
            // Needs update if lengths differ.
            $current_length = intval($lengthy_current[2]);
            $specified_length = intval($lengthy_specified[2]);
            return $current_length != $specified_length;
        }
    }

    /**
    * @desc Syncronizes a table in the database with
    * @desc the generic table model used by nanoMVC.
    * @param String $raw_table_name The raw table name, the identifier without prefixing.
    * @param Model $example_model An example of the model instance of the table to sync.
    */
    public static function sync_table_layout_with_model($raw_table_name, $example_model) {
        if (api_database::$initialized == false)
            api_database::init();
        // Make an array where [name] => sql_type
        $columns = array();
        // Check names and fetches types.
        foreach ($example_model->getColumns() as $name => $column) {
            self::verify_keyword($name);
            $columns[strtolower($name)] = $column->getSQLType();
        }
        self::sync_table_layout_with_columns($raw_table_name, $columns);
    }

    /**
    *@desc Returns all tables in the database.
    */
    public static function get_all_tables() {
        // Gets all tables in the database.
        static $all_tables = null;
        if ($all_tables === null) {
            $all_tables = array();
            $all_tables_query = self::query("SHOW TABLES");
            while (false !== ($table = self::next_array($all_tables_query)))
                $all_tables[] = strtolower($table[0]);
        }
        return $all_tables;
    }

    /**
    * @desc Syncronizes a table in the database with the given column structure.
    * @param String $table_name The literal name of the table in the database.
    * @param Array $columns Array of columns mapped to their SQL types, eg "total => int(11), ...".
    */
    public static function sync_table_layout_with_columns($raw_table_name, $columns) {
        $table_name = _tblprefix . strtolower($raw_table_name);
        $all_tables = self::get_all_tables();
        if (in_array($table_name, $all_tables)) {
            // Altering existing table.
            $current_columns = self::query("DESCRIBE `$table_name`");
            while (false !== ($column = self::next_array($current_columns))) {
                $current_name = strtolower($column[0]);
                $current_type = strtolower($column[1]);
                $expected_type = $columns[$current_name];
                // ID column is special case.
                if ($current_name == 'id') {
                    if (!api_string::starts_with($current_type, "int"))
                        throw new Exception("ID column found in $table_name, but with unexpected type ($current_type). Has it been tampered with? Script not written to handle this condition.");
                    continue;
                }
                if (!isset($columns[$current_name])) {
                    // Unknown column, drop it.
                    self::query("ALTER TABLE `$table_name` DROP COLUMN $current_name");
                    continue;
                } else if (self::sql_column_need_update($expected_type, $current_type)) {
                    // Invalid datatype, alter it.
                    self::query("ALTER TABLE `$table_name` MODIFY COLUMN $current_name $expected_type");
                }
                // This column was confirmed.
                unset($columns[$current_name]);
            }
            // Insert the rest of the columns.
            $adds = array();
            foreach ($columns as $name => $type)
                $adds[] = "$name $type";
            $adds = implode(", ", $adds);
            if (strlen($adds) > 0)
                self::query("ALTER TABLE `$table_name` ADD ($adds)");
            // Finally make sure ID column is correct.
        } else {
            // Creating new table.
            $adds = 'id int NOT NULL AUTO_INCREMENT, PRIMARY KEY (id)';
            foreach ($columns as $name => $type)
                $adds .= ", $name $type";
            self::query("CREATE TABLE `$table_name` ( $adds )");
        }
    }

    private static $keywords_mssql =        array("ADD","ALL","ALTER","AND","ANY","AS","ASC","AUTHORIZATION","BACKUP","BEGIN","BETWEEN","BREAK","BROWSE","BULK","BY","CASCADE","CASE","CHECK","CHECKPOINT","CLOSE","CLUSTERED","COALESCE","COLLATE","COLUMN","COMMIT","COMPUTE","CONSTRAINT","CONTAINS","CONTAINSTABLE","CONTINUE","CONVERT","CREATE","CROSS","CURRENT","CURRENT_DATE","CURRENT_TIME","CURRENT_TIMESTAMP","CURRENT_USER","CURSOR","DATABASE","DBCC","DEALLOCATE","DECLARE","DEFAULT","DELETE","DENY","DESC","DISK","DISTINCT","DISTRIBUTED","DOUBLE","DROP","DUMMY","DUMP","ELSE","END","ERRLVL","ESCAPE","EXCEPT","EXEC","EXECUTE","EXISTS","EXIT","FETCH","FILE","FILLFACTOR","FOR","FOREIGN","FREETEXT","FREETEXTTABLE","FROM","FULL","FUNCTION","GOTO","GRANT","GROUP","HAVING","HOLDLOCK","IDENTITY","IDENTITY_INSERT","IDENTITYCOL","IF","IN","INDEX","INNER","INSERT","INTERSECT","INTO","IS","JOIN","KEY","KILL","LEFT","LIKE","LINENO","LOAD","NATIONAL","NOCHECK","NONCLUSTERED","NOT","NULL","NULLIF","OF","OFF","OFFSETS","ON","OPEN","OPENDATASOURCE","OPENQUERY","OPENROWSET","OPENXML","OPTION","OR","ORDER","OUTER","OVER","PERCENT","PLAN","PRECISION","PRIMARY","PRINT","PROC","PROCEDURE","PUBLIC","RAISERROR","READ","READTEXT","RECONFIGURE","REFERENCES","REPLICATION","RESTORE","RESTRICT","RETURN","REVOKE","RIGHT","ROLLBACK","ROWCOUNT","ROWGUIDCOL","RULE","SAVE","SCHEMA","SELECT","SESSION_USER","SET","SETUSER","SHUTDOWN","SOME","STATISTICS","SYSTEM_USER","TABLE","TEXTSIZE","THEN","TO","TOP","TRAN","TRANSACTION","TRIGGER","TRUNCATE","TSEQUAL","UNION","UNIQUE","UPDATE","UPDATETEXT","USE","USER","VALUES","VARYING","VIEW","WAITFOR","WHEN","WHERE","WHILE","WITH","WRITETEXT");
    private static $keywords_odbc =         array("ABSOLUTE","ACTION","ADA","ADD","ALL","ALLOCATE",    "ALTER","AND","ANY","ARE","AS","ASC","ASSERTION","AT","AUTHORIZATION","AVG","BEGIN","BETWEEN","BIT","BIT_LENGTH","BOTH","BY","CASCADE","CASCADED","CASE","CAST","CATALOG","CHAR","CHAR_LENGTH","CHARACTER","CHARACTER_LENGTH","CHECK","CLOSE","COALESCE","COLLATE","COLLATION","COLUMN","COMMIT","CONNECT","CONNECTION","CONSTRAINT","CONSTRAINTS","CONTINUE","CONVERT","CORRESPONDING","COUNT","CREATE","CROSS","CURRENT","CURRENT_DATE","CURRENT_TIME","CURRENT_TIMESTAMP","CURRENT_USER","CURSOR","DATE","DAY","DEALLOCATE","DEC","DECIMAL","DECLARE","DEFAULT","DEFERRABLE","DEFERRED","DELETE","DESC","DESCRIBE","DESCRIPTOR","DIAGNOSTICS","DISCONNECT","DISTINCT","DOMAIN","DOUBLE","DROP","ELSE","END","END-EXEC","ESCAPE","EXCEPT","EXCEPTION","EXEC","EXECUTE","EXISTS","EXTERNAL","EXTRACT","FALSE","FETCH","FIRST","FLOAT","FOR","FOREIGN","FORTRAN","FOUND","FROM","FULL","GET","GLOBAL","GO","GOTO","GRANT","GROUP","HAVING","HOUR","IDENTITY","IMMEDIATE","IN","INCLUDE","INDEX","INDICATOR","INITIALLY","INNER","INPUT","INSENSITIVE","INSERT","INT","INTEGER","INTERSECT","INTERVAL","INTO","IS","ISOLATION","JOIN","KEY","LANGUAGE","LAST","LEADING","LEFT","LEVEL","LIKE","LOCAL","LOWER","MATCH","MAX","MIN","MINUTE","MODULE","MONTH","NAMES","NATIONAL","NATURAL","NCHAR","NEXT","NO","NONE","NOT","NULL","NULLIF","NUMERIC","OCTET_LENGTH","OF","ON","ONLY","OPEN","OPTION","OR","ORDER","OUTER","OUTPUT","OVERLAPS","PAD","PARTIAL","PASCAL","POSITION","PRECISION","PREPARE","PRESERVE","PRIMARY","PRIOR","PRIVILEGES","PROCEDURE","PUBLIC","READ","REAL","REFERENCES","RELATIVE","RESTRICT","REVOKE","RIGHT",
                                                  "ROLLBACK","ROWS","SCHEMA","SCROLL","SECOND","SECTION","SELECT","SESSION","SESSION_USER","SET","SIZE","SMALLINT","SOME","SPACE","SQL","SQLCA","SQLCODE","SQLERROR","SQLSTATE","SQLWARNING","SUBSTRING","SUM","SYSTEM_USER","TABLE","TEMPORARY","THEN","TIME","TIMESTAMP","TIMEZONE_HOUR","TIMEZONE_MINUTE","TO","TRAILING","TRANSACTION","TRANSLATE","TRANSLATION","TRIM","TRUE","UNION","UNIQUE","UNKNOWN","UPDATE","UPPER","USAGE","USER","USING","VALUE","VALUES","VARCHAR","VARYING","VIEW","WHEN","WHENEVER","WHERE","WITH","WORK","WRITE","YEAR","ZONE");
    private static $keywords_mssql_future = array("ABSOLUTE","ACTION","ADMIN","AFTER","AGGREGATE","ALIAS","ALLOCATE","ARE",    "ARRAY","ASSERTION","AT","BEFORE","BINARY","BIT","BLOB","BOOLEAN","BOTH","BREADTH","CALL","CASCADED","CAST","CATALOG","CHAR","CHARACTER","CLASS","CLOB","COLLATION","COMPLETION","CONNECT","CONNECTION","CONSTRAINTS","CONSTRUCTOR","CORRESPONDING","CUBE","CURRENT_PATH","CURRENT_ROLE","CYCLE","DATA","DATE","DAY","DEC","DECIMAL","DEFERRABLE","DEFERRED","DEPTH","DEREF","DESCRIBE","DESCRIPTOR","DESTROY","DESTRUCTOR","DETERMINISTIC","DICTIONARY","DIAGNOSTICS","DISCONNECT","DOMAIN","DYNAMIC","EACH","END-EXEC","EQUALS","EVERY","EXCEPTION","EXTERNAL","FALSE","FIRST","FLOAT","FOUND","FREE","GENERAL","GET","GLOBAL","GO","GROUPING","HOST","HOUR","IGNORE","IMMEDIATE","INDICATOR","INITIALIZE","INITIALLY","INOUT","INPUT","INT","INTEGER","INTERVAL","ISOLATION","ITERATE","LANGUAGE","LARGE","LAST","LATERAL","LEADING","LESS","LEVEL","LIMIT","LOCAL","LOCALTIME","LOCALTIMESTAMP","LOCATOR","MAP","MATCH","MINUTE","MODIFIES","MODIFY","MODULE","MONTH","NAMES","NATURAL","NCHAR","NCLOB","NEW","NEXT","NO","NONE","NUMERIC","OBJECT","OLD","ONLY","OPERATION","ORDINALITY","OUT","OUTPUT","PAD","PARAMETER","PARAMETERS","PARTIAL","PATH","POSTFIX","PREFIX","PREORDER","PREPARE","PRESERVE","PRIOR","PRIVILEGES","READS","REAL","RECURSIVE","REF","REFERENCING","RELATIVE","RESULT","RETURNS","ROLE","ROLLUP","ROUTINE","ROW","ROWS","SAVEPOINT","SCROLL","SCOPE","SEARCH","SECOND","SECTION","SEQUENCE","SESSION","SETS","SIZE","SMALLINT","SPACE","SPECIFIC","SPECIFICTYPE","SQL","SQLEXCEPTION","SQLSTATE","SQLWARNING","START","STATE","STATEMENT","STATIC","STRUCTURE","TEMPORARY",
                                                  "TERMINATE","THAN","TIME","TIMESTAMP","TIMEZONE_HOUR","TIMEZONE_MINUTE","TRAILING","TRANSLATION","TREAT","TRUE","UNDER","UNKNOWN","UNNEST","USAGE","USING","VALUE","VARCHAR","VARIABLE","WHENEVER","WITHOUT","WORK","WRITE","YEAR","ZONE");
    private static $keywords_mysql =        array("ADD","ANALYZE","ASC","BETWEEN","BLOB","CALL","CHANGE","CHECK","CONDITION","CONVERT",    "CURRENT_DATE","CURRENT_USER","DATABASES","DAY_MINUTE","DECIMAL","DELAYED","DESCRIBE","DISTINCTROW","DROP","ELSE","ESCAPED","EXPLAIN","FLOAT","FOR","FROM","GROUP","HOUR_MICROSECOND","IF","INDEX","INOUT","INT","INT3","INTEGER","IS","KEY","LEADING","LIKE","LOAD","LOCK","LONGTEXT","MATCH","MEDIUMTEXT","MINUTE_SECOND","NATURAL","NULL","OPTIMIZE","OR","OUTER","PRIMARY","READ","REFERENCES","RENAME","REQUIRE","REVOKE","SCHEMA","SELECT","SET","SONAME","SQL","SQLWARNING","SQL_SMALL_RESULT","STRAIGHT_JOIN","THEN","TINYTEXT","TRIGGER","UNION","UNSIGNED","USE","UTC_TIME","VARBINARY","VARYING","WHILE","XOR","ALL","AND","ASENSITIVE","BIGINT","BOTH","CASCADE","CHAR","COLLATE","CONSTRAINT","CREATE","CURRENT_TIME","CURSOR","DAY_HOUR","DAY_SECOND","DECLARE","DELETE","DETERMINISTIC","DIV","DUAL","ELSEIF","EXISTS","FALSE","FLOAT4","FORCE","FULLTEXT","HAVING","HOUR_MINUTE","IGNORE","INFILE","INSENSITIVE","INT1","INT4","INTERVAL","ITERATE","KEYS","LEAVE","LIMIT","LOCALTIME","LONG","LOOP","MEDIUMBLOB","MIDDLEINT","MOD","NOT","NUMERIC","OPTION","ORDER","OUTFILE","PROCEDURE","READS","REGEXP","REPEAT","RESTRICT","RIGHT","SCHEMAS","SENSITIVE","SHOW","SPATIAL","SQLEXCEPTION","SQL_BIG_RESULT","SSL","TABLE","TINYBLOB","TO","TRUE","UNIQUE","UPDATE","USING","UTC_TIMESTAMP","VARCHAR","WHEN","WITH","YEAR_MONTH","ALTER","AS","BEFORE","BINARY","BY","CASE","CHARACTER","COLUMN","CONTINUE","CROSS","CURRENT_TIMESTAMP","DATABASE","DAY_MICROSECOND","DEC","DEFAULT","DESC","DISTINCT","DOUBLE","EACH","ENCLOSED","EXIT","FETCH","FLOAT8","FOREIGN","GRANT",
                                                  "HIGH_PRIORITY","HOUR_SECOND","IN","INNER","INSERT","INT2","INT8","INTO","JOIN","KILL","LEFT","LINES","LOCALTIMESTAMP","LONGBLOB","LOW_PRIORITY","MEDIUMINT","MINUTE_MICROSECOND","MODIFIES","NO_WRITE_TO_BINLOG","ON","OPTIONALLY","OUT","PRECISION","PURGE","REAL","RELEASE","REPLACE","RETURN","RLIKE","SECOND_MICROSECOND","SEPARATOR","SMALLINT","SPECIFIC","SQLSTATE","SQL_CALC_FOUND_ROWS","STARTING","TERMINATED","TINYINT","TRAILING","UNDO","UNLOCK","USAGE","UTC_DATE","VALUES","VARCHARACTER","WHERE","WRITE","ZEROFILL");
    private static $keywords_mysql_new =    array("ASENSITIVE","CONNECTION","DECLARE","ELSEIF","GOTO","ITERATE","LOOP","READS","RETURN","SENSITIVE","SQLEXCEPTION","TRIGGER","WHILE","CALL","CONTINUE","DETERMINISTIC","EXIT","INOUT","LABEL","MODIFIES","RELEASE","SCHEMA","SPECIFIC","SQLSTATE","UNDO","CONDITION","CURSOR","EACH","FETCH","INSENSITIVE","LEAVE","OUT","REPEAT","SCHEMAS","SQL","SQLWARNING","UPGRADE");
    private static $keywords_mysql_allowed= array("ACTION","BIT","DATE","ENUM","NO","TEXT","TIME","TIMESTAMP");

    private static function verify_keyword($word) {
        $word = strtoupper($word);
        if (in_array($word, self::$keywords_mssql))
            $err = 'msSQL Keywords';
        else if (in_array($word, self::$keywords_odbc))
            $err = 'ODBC Keywords';
        else if (in_array($word, self::$keywords_mssql_future))
            $err = 'msSQL Future Keywords';
        else if (in_array($word, self::$keywords_mysql))
            $err = 'mySQL Keywords';
        else if (in_array($word, self::$keywords_mysql_new))
            $err = 'mySQL New Keywords (v.5)';
        else if (in_array($word, self::$keywords_mysql_allowed))
            $err = 'Keywords to Avoid (Depricated)';
        else return;
        throw new Exception("The identifier name you used '$word' was detected to be reserved by the list '$err'. Using that identifier could break some SQL queries.");
    }


    private static function init() {
        // Connect to the database.
        switch (CONFIG::$sql_driver) {
            case api_database::DRIVER_MYSQL:
                $db_handle = mysql_connect(CONFIG::$sql_host, CONFIG::$sql_user, CONFIG::$sql_password)
                    or panic("The mySQL connection could not be established. " . mysql_error());
                break;
            case api_database::DRIVER_MSSQL:
                $db_handle = mssql_connect(CONFIG::$sql_host, CONFIG::$sql_user, CONFIG::$sql_password)
                    or panic("The msSQL connection could not be established. " . mssql_get_last_message());
                break;
        }

        // Throw away magic quotes, the standard database injection protection for badly written PHP code.
        if (set_magic_quotes_runtime(0) === FALSE)
            throw new Exception("Unable to disable magic_quotes_runtime ini option!");

        // Using a stripslashes callback for any gpc data.
        if (get_magic_quotes_gpc()) {
            function stripslashes_deep($value) {
                $value = is_array($value)?array_map('stripslashes_deep', $value):stripslashes($value);
                return $value;
            }
            $_POST = array_map('stripslashes_deep', $_POST);
            $_GET = array_map('stripslashes_deep', $_GET);
            $_COOKIE = array_map('stripslashes_deep', $_COOKIE);
            $_REQUEST = array_map('stripslashes_deep', $_REQUEST);
        }

        // Define database connection constants.
        define('_vcms_dbdriver', CONFIG::$sql_driver);
        define('_vcms_dbhandle', $db_handle);

        api_database::$initialized = true;

        // USE the configured database.
        api_database::query("USE " . CONFIG::$sql_database);
    }
}

?>