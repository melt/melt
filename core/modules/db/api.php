<?php namespace nmvc\db;

/**
 * @desc Calling this function enables buffer printing of all SQL queries.
 *       Useful for SQL batch scripts.
 */
function enable_display() {
    \nmvc\request\reset();
    header('Content-Type: text/plain');
    define("OUTPUT_MYSQL_QUERIES", true);
    ob_end_clean();
}

function get_auto_increment($table) {
    $r = query("SELECT AUTO_INCREMENT FROM information_schema.tables WHERE table_name='".$table."'");
    $r = next_array($r);
    return $r[0];
}

function set_auto_increment($table, $id, $errmsg = null) {
    query("ALTER TABLE `" . $table . "` AUTO_INCREMENT = ".intval($id), $errmsg);
}

/**
 * @desc Get the ID generated from the previous INSERT operation
 * @return The ID generated for an AUTO_INCREMENT column by the previous INSERT
 * query on success, 0 if the previous query does not generate an
 * AUTO_INCREMENT value, or FALSE if no MySQL connection was established.
 */
function insert_id() {
    $r = query("SELECT LAST_INSERT_ID()");
    $r = next_array($r);
    return $r[0];
}

/**
 * This function properly escapes and quotes any string you insert,
 * making it ready to be directly inserted into your SQL queries.
 * @example Input: a 'test' Output: "a \'test\'"
 * @param string $string
 * @param integer $max_length Maximum length of string in charachers.
 * Only use this parameter if string contains UTF-8 text and not binary data.
 * @return string The escaped and quoted string you inputed.
 */
function strfy($string, $max_length = null) {
    if ($max_length !== null && $max_length >= 0)
        $string = iconv_substr($string, 0, $max_length);
    // Have to connect for mysql_real_escape_string to work.
    if (!defined("NANOMVC_DB_LINK"))
        run(";");
    return '"' . mysql_real_escape_string($string) . '"';
}

/**
 * @desc Queries the database, and throws specified error on failure.
 * @desc It will throw an exception if query fails.
 * @param String $query The SQL query.
 * @param String $errmsg Additional information about the action that will be
 * thrown if query fails.
 * @see To query without errorhandling, use db\run().
 * @return mixed Returns a result resource handle on success, TRUE if no rows were returned, or FALSE on error.
 */
function query($query, $errmsg = "") {
    $result = run($query);
    if ($result === FALSE) {
        $err = mysql_error();
        if (strlen($query) > 512)
            $query = substr($query, 0, 512);
        $query = var_export($query, true);
        if ($errmsg == "")
            $errmsg = "A SQL query { ".$query." } to the database failed;\nSQL error: ".$err;
        else
            $errmsg = "A SQL query { ".$query." } to the database failed;\nOperation information:\n".$errmsg."\nSQL error: ".$err;
        if (!defined("OUTPUT_MYSQL_QUERIES"))
            trigger_error($errmsg, \E_USER_ERROR);
        else
            echo $errmsg;
        exit;
    }
    return $result;
}
/**
 * @desc Runs a query on the database, ignoring any failure.
 * @param String $query The SQL query to execute on acms DB connection.
 * @see To query with errorhandling, use db\query().
 * @return mixed A result resource handle on success, TRUE if no rows were returned, or FALSE on error.
 */
function run($query) {
    static $initialized = false;
    if (!$initialized) {
        // Make sure mysql extention is loaded.
        if (!extension_loaded("mysql"))
            trigger_error("Error: The MySQL extention is not loaded. Your PHP installation is not compatible with nanoMVC!", \E_USER_ERROR);
        // Can spend 10 seconds max connecting or half the request time limit.
        $max_mysql_timeout = intval(ini_get("max_execution_time"))  / 2;
        if ($max_mysql_timeout > 10)
            $max_mysql_timeout = 10;
        ini_set("mysql.connect_timeout", $max_mysql_timeout);
        // Connect to the database.
        $link = mysql_connect(config\HOST, config\USER, config\PASSWORD);
        if ($link === false)
            trigger_error("The mySQL connection could not be established. " . mysql_error(), \E_USER_ERROR);
        define("NANOMVC_DB_LINK", $link);
        mysql_set_charset('utf8');
        // Throw away magic quotes, the standard database injection protection for badly written PHP code.
        if (ini_get("magic_quotes_runtime") && set_magic_quotes_runtime(0) === FALSE)
            trigger_error("Unable to disable magic_quotes_runtime ini option!", \E_USER_ERROR);
        // Using a stripslashes callback for any gpc data.
        if (get_magic_quotes_gpc()) {
            function _stripslashes_deep($value) {
                $value = is_array($value)? array_map('\nmvc\db\_stripslashes_deep', $value): stripslashes($value);
                return $value;
            }
            $_POST = array_map('\nmvc\db\_stripslashes_deep', $_POST);
            $_GET = array_map('\nmvc\db\_stripslashes_deep', $_GET);
            $_COOKIE = array_map('\nmvc\db\_stripslashes_deep', $_COOKIE);
            $_REQUEST = array_map('\nmvc\db\_stripslashes_deep', $_REQUEST);
        }
        // USE the configured database.
        if (strlen(config\NAME) == 0)
            throw new \Exception("No database name specified!");
        $initialized = true;
        query("USE " . config\NAME);
    }
    if (defined("OUTPUT_MYSQL_QUERIES")) {
        echo $query . "\r\n";
        ob_flush();
    }
    if (!config\DEBUG_QUERY_BENCHMARK)
        return mysql_query($query);
    // Benchmark query and log.
    file_put_contents(APP_DIR . "/db_debug_query_benchmark.log", date("r") . ": $query\n", FILE_APPEND);
    $time_start = microtime(true);
    $ret = mysql_query($query);
    $total = microtime(true) - $time_start;
    file_put_contents(APP_DIR . "/db_debug_query_benchmark.log",  "^-" . round($total * 1000, 1) . " ms\n\n", FILE_APPEND);
    return $ret;
}

/** Returns the number of affected rows in the last query. */
function affected_rows() {
    return mysql_affected_rows();
}

/** Number of rows in result. */
function get_num_rows($result) {
    return mysql_numrows($result);
}

/** Number of columns in result. */
function get_num_cols($result) {
    return mysql_numfields($result);
}

/** Returns the next row in result as an associative array,
 * or FALSE if there are no more rows. */
function next_assoc($result) {
    return mysql_fetch_assoc($result);
}

/** Seeks the result position to row n. */
function data_seek($result, $n) {
    return mysql_data_seek($result, $n);
}

/**
* @return The next row in result as a numeric array, or FALSE if there are no more rows.
*/
function next_array($result) {
    return mysql_fetch_array($result, MYSQL_NUM);
}

/**
* @desc Returns false if the current column is a subset of the specified column.
* If current is int(9) or int(123) and specified is int, this returns false.
* However, if current is int(23) and specified is int(12), this returns true.
*/
function sql_column_need_update($specified, $current) {
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
* @param String $table_name The raw table name, the identifier without prefixing.
* @param array $parsed_col_array Parsed column array of model.
*/
function sync_table_layout_with_model($table_name, $parsed_col_array) {
    // Make an array where [name] => sql_type
    $columns = array();
    // Check names and fetches types.
    foreach ($parsed_col_array as $name => $column) {
        verify_keyword($name);
        $columns[strtolower($name)] = $column->getSQLType();
    }
    sync_table_layout_with_columns($table_name, $columns);
}

/** Returns all tables in the database. */
function get_all_tables() {
    // Gets all tables in the database.
    static $all_tables = null;
    if ($all_tables === null) {
        $all_tables = array();
        $all_tables_query = query("SHOW TABLES");
        while (false !== ($table = next_array($all_tables_query)))
            $all_tables[] = strtolower($table[0]);
    }
    return $all_tables;
}

/**
* @desc Syncronizes a table in the database with the given column structure.
* @param String $table_name The literal name of the table in the database.
* @param Array $columns Array of columns mapped to their SQL types, eg "total => int(11), ...".
*/
function sync_table_layout_with_columns($table_name, $columns) {
    $all_tables = get_all_tables();
    if (in_array(substr(table($table_name), 1, -1), $all_tables)) {
        // Altering existing table.
        $current_columns = query("DESCRIBE " . table($table_name));
        while (false !== ($column = next_array($current_columns))) {
            $current_name = strtolower($column[0]);
            $current_type = strtolower($column[1]);
            // ID column is special case.
            if ($current_name == 'id') {
                if (!\nmvc\string\starts_with($current_type, "int"))
                    trigger_error("ID column found in table '$table_name', but with unexpected type ($current_type). Has it been tampered with? nanoMVC can not handle this exception automatically.", \E_USER_ERROR);
                continue;
            }
            // Skip unknown columns.
            if (!isset($columns[$current_name]))
                continue;
            $expected_type = $columns[$current_name];
            if (isset($columns[$current_name]) && sql_column_need_update($expected_type, $current_type)) {
                // Invalid datatype, alter it.
                query("ALTER TABLE " . table($table_name) . " MODIFY COLUMN $current_name $expected_type");
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
            query("ALTER TABLE " . table($table_name) . " ADD ($adds)");
    } else {
        // Creating new table.
        $adds = 'id INT UNSIGNED NOT NULL, PRIMARY KEY (id)';
        foreach ($columns as $name => $type)
            $adds .= ", $name $type";
        query("CREATE TABLE " . table($table_name) . " ( $adds )");
    }
    // Finally reset the auto id trigger.
    $trigger = table("aid_trigger_" . $table_name);
    run("DROP TRIGGER " . $trigger);
    if (config\USE_TRIGGER_SEQUENCING) {
        $result = run("CREATE TRIGGER " . $trigger . " BEFORE INSERT ON " . table($table_name) . " FOR EACH ROW BEGIN UPDATE " . table('core\seq') . " SET id = LAST_INSERT_ID(id+1); SET @last_insert = LAST_INSERT_ID(); SET NEW.id = @last_insert; END;");
        if ($result === false)
            trigger_error("Adding trigger failed. Probably due to lack of TRIGGER permission or old mySQL version. Either GRANT TRIGGER permissions to current user or set USE_TRIGGER_SEQUENCING to false in config.php to use a slower method that doesn't protect against data corruption from to duplicate table spanning ID's (by other mySQL software).", \E_USER_ERROR);
    }
}

function verify_keyword($word) {
    $keywords_mssql =        array("ADD","ALL","ALTER","AND","ANY","AS","ASC","AUTHORIZATION","BACKUP","BEGIN","BETWEEN","BREAK","BROWSE","BULK","BY","CASCADE","CASE","CHECK","CHECKPOINT","CLOSE","CLUSTERED","COALESCE","COLLATE","COLUMN","COMMIT","COMPUTE","CONSTRAINT","CONTAINS","CONTAINSTABLE","CONTINUE","CONVERT","CREATE","CROSS","CURRENT","CURRENT_DATE","CURRENT_TIME","CURRENT_TIMESTAMP","CURRENT_USER","CURSOR","DATABASE","DBCC","DEALLOCATE","DECLARE","DEFAULT","DELETE","DENY","DESC","DISK","DISTINCT","DISTRIBUTED","DOUBLE","DROP","DUMMY","DUMP","ELSE","END","ERRLVL","ESCAPE","EXCEPT","EXEC","EXECUTE","EXISTS","EXIT","FETCH","FILE","FILLFACTOR","FOR","FOREIGN","FREETEXT","FREETEXTTABLE","FROM","FULL","FUNCTION","GOTO","GRANT","GROUP","HAVING","HOLDLOCK","IDENTITY","IDENTITY_INSERT","IDENTITYCOL","IF","IN","INDEX","INNER","INSERT","INTERSECT","INTO","IS","JOIN","KEY","KILL","LEFT","LIKE","LINENO","LOAD","NATIONAL","NOCHECK","NONCLUSTERED","NOT","NULL","NULLIF","OF","OFF","OFFSETS","ON","OPEN","OPENDATASOURCE","OPENQUERY","OPENROWSET","OPENXML","OPTION","OR","ORDER","OUTER","OVER","PERCENT","PLAN","PRECISION","PRIMARY","PRINT","PROC","PROCEDURE","PUBLIC","RAISERROR","READ","READTEXT","RECONFIGURE","REFERENCES","REPLICATION","RESTORE","RESTRICT","RETURN","REVOKE","RIGHT","ROLLBACK","ROWCOUNT","ROWGUIDCOL","RULE","SAVE","SCHEMA","SELECT","SESSION_USER","SET","SETUSER","SHUTDOWN","SOME","STATISTICS","SYSTEM_USER","TABLE","TEXTSIZE","THEN","TO","TOP","TRAN","TRANSACTION","TRIGGER","TRUNCATE","TSEQUAL","UNION","UNIQUE","UPDATE","UPDATETEXT","USE","USER","VALUES","VARYING","VIEW","WAITFOR","WHEN","WHERE","WHILE","WITH","WRITETEXT");
    $keywords_odbc =         array("ABSOLUTE","ACTION","ADA","ADD","ALL","ALLOCATE",    "ALTER","AND","ANY","ARE","AS","ASC","ASSERTION","AT","AUTHORIZATION","AVG","BEGIN","BETWEEN","BIT","BIT_LENGTH","BOTH","BY","CASCADE","CASCADED","CASE","CAST","CATALOG","CHAR","CHAR_LENGTH","CHARACTER","CHARACTER_LENGTH","CHECK","CLOSE","COALESCE","COLLATE","COLLATION","COLUMN","COMMIT","CONNECT","CONNECTION","CONSTRAINT","CONSTRAINTS","CONTINUE","CONVERT","CORRESPONDING","COUNT","CREATE","CROSS","CURRENT","CURRENT_DATE","CURRENT_TIME","CURRENT_TIMESTAMP","CURRENT_USER","CURSOR","DATE","DAY","DEALLOCATE","DEC","DECIMAL","DECLARE","DEFAULT","DEFERRABLE","DEFERRED","DELETE","DESC","DESCRIBE","DESCRIPTOR","DIAGNOSTICS","DISCONNECT","DISTINCT","DOMAIN","DOUBLE","DROP","ELSE","END","END-EXEC","ESCAPE","EXCEPT","EXCEPTION","EXEC","EXECUTE","EXISTS","EXTERNAL","EXTRACT","FALSE","FETCH","FIRST","FLOAT","FOR","FOREIGN","FORTRAN","FOUND","FROM","FULL","GET","GLOBAL","GO","GOTO","GRANT","GROUP","HAVING","HOUR","IDENTITY","IMMEDIATE","IN","INCLUDE","INDEX","INDICATOR","INITIALLY","INNER","INPUT","INSENSITIVE","INSERT","INT","INTEGER","INTERSECT","INTERVAL","INTO","IS","ISOLATION","JOIN","KEY","LANGUAGE","LAST","LEADING","LEFT","LEVEL","LIKE","LOCAL","LOWER","MATCH","MAX","MIN","MINUTE","MODULE","MONTH","NAMES","NATIONAL","NATURAL","NCHAR","NEXT","NO","NONE","NOT","NULL","NULLIF","NUMERIC","OCTET_LENGTH","OF","ON","ONLY","OPEN","OPTION","OR","ORDER","OUTER","OUTPUT","OVERLAPS","PAD","PARTIAL","PASCAL","POSITION","PRECISION","PREPARE","PRESERVE","PRIMARY","PRIOR","PRIVILEGES","PROCEDURE","PUBLIC","READ","REAL","REFERENCES","RELATIVE","RESTRICT","REVOKE","RIGHT",
                                                  "ROLLBACK","ROWS","SCHEMA","SCROLL","SECOND","SECTION","SELECT","SESSION","SESSION_USER","SET","SIZE","SMALLINT","SOME","SPACE","SQL","SQLCA","SQLCODE","SQLERROR","SQLSTATE","SQLWARNING","SUBSTRING","SUM","SYSTEM_USER","TABLE","TEMPORARY","THEN","TIME","TIMESTAMP","TIMEZONE_HOUR","TIMEZONE_MINUTE","TO","TRAILING","TRANSACTION","TRANSLATE","TRANSLATION","TRIM","TRUE","UNION","UNIQUE","UNKNOWN","UPDATE","UPPER","USAGE","USER","USING","VALUE","VALUES","VARCHAR","VARYING","VIEW","WHEN","WHENEVER","WHERE","WITH","WORK","WRITE","YEAR","ZONE");
    $keywords_mssql_future = array("ABSOLUTE","ACTION","ADMIN","AFTER","AGGREGATE","ALIAS","ALLOCATE","ARE",    "ARRAY","ASSERTION","AT","BEFORE","BINARY","BIT","BLOB","BOOLEAN","BOTH","BREADTH","CALL","CASCADED","CAST","CATALOG","CHAR","CHARACTER","CLASS","CLOB","COLLATION","COMPLETION","CONNECT","CONNECTION","CONSTRAINTS","CONSTRUCTOR","CORRESPONDING","CUBE","CURRENT_PATH","CURRENT_ROLE","CYCLE","DATA","DATE","DAY","DEC","DECIMAL","DEFERRABLE","DEFERRED","DEPTH","DEREF","DESCRIBE","DESCRIPTOR","DESTROY","DESTRUCTOR","DETERMINISTIC","DICTIONARY","DIAGNOSTICS","DISCONNECT","DOMAIN","DYNAMIC","EACH","END-EXEC","EQUALS","EVERY","EXCEPTION","EXTERNAL","FALSE","FIRST","FLOAT","FOUND","FREE","GENERAL","GET","GLOBAL","GO","GROUPING","HOST","HOUR","IGNORE","IMMEDIATE","INDICATOR","INITIALIZE","INITIALLY","INOUT","INPUT","INT","INTEGER","INTERVAL","ISOLATION","ITERATE","LANGUAGE","LARGE","LAST","LATERAL","LEADING","LESS","LEVEL","LIMIT","LOCAL","LOCALTIME","LOCALTIMESTAMP","LOCATOR","MAP","MATCH","MINUTE","MODIFIES","MODIFY","MODULE","MONTH","NAMES","NATURAL","NCHAR","NCLOB","NEW","NEXT","NO","NONE","NUMERIC","OBJECT","OLD","ONLY","OPERATION","ORDINALITY","OUT","OUTPUT","PAD","PARAMETER","PARAMETERS","PARTIAL","PATH","POSTFIX","PREFIX","PREORDER","PREPARE","PRESERVE","PRIOR","PRIVILEGES","READS","REAL","RECURSIVE","REF","REFERENCING","RELATIVE","RESULT","RETURNS","ROLE","ROLLUP","ROUTINE","ROW","ROWS","SAVEPOINT","SCROLL","SCOPE","SEARCH","SECOND","SECTION","SEQUENCE","SESSION","SETS","SIZE","SMALLINT","SPACE","SPECIFIC","SPECIFICTYPE","SQL","SQLEXCEPTION","SQLSTATE","SQLWARNING","START","STATE","STATEMENT","STATIC","STRUCTURE","TEMPORARY",
                                                  "TERMINATE","THAN","TIME","TIMESTAMP","TIMEZONE_HOUR","TIMEZONE_MINUTE","TRAILING","TRANSLATION","TREAT","TRUE","UNDER","UNKNOWN","UNNEST","USAGE","USING","VALUE","VARCHAR","VARIABLE","WHENEVER","WITHOUT","WORK","WRITE","YEAR","ZONE");
    $keywords_mysql =        array("ADD","ANALYZE","ASC","BETWEEN","BLOB","CALL","CHANGE","CHECK","CONDITION","CONVERT",    "CURRENT_DATE","CURRENT_USER","DATABASES","DAY_MINUTE","DECIMAL","DELAYED","DESCRIBE","DISTINCTROW","DROP","ELSE","ESCAPED","EXPLAIN","FLOAT","FOR","FROM","GROUP","HOUR_MICROSECOND","IF","INDEX","INOUT","INT","INT3","INTEGER","IS","KEY","LEADING","LIKE","LOAD","LOCK","LONGTEXT","MATCH","MEDIUMTEXT","MINUTE_SECOND","NATURAL","NULL","OPTIMIZE","OR","OUTER","PRIMARY","READ","REFERENCES","RENAME","REQUIRE","REVOKE","SCHEMA","SELECT","SET","SONAME","SQL","SQLWARNING","SQL_SMALL_RESULT","STRAIGHT_JOIN","THEN","TINYTEXT","TRIGGER","UNION","UNSIGNED","USE","UTC_TIME","VARBINARY","VARYING","WHILE","XOR","ALL","AND","ASENSITIVE","BIGINT","BOTH","CASCADE","CHAR","COLLATE","CONSTRAINT","CREATE","CURRENT_TIME","CURSOR","DAY_HOUR","DAY_SECOND","DECLARE","DELETE","DETERMINISTIC","DIV","DUAL","ELSEIF","EXISTS","FALSE","FLOAT4","FORCE","FULLTEXT","HAVING","HOUR_MINUTE","IGNORE","INFILE","INSENSITIVE","INT1","INT4","INTERVAL","ITERATE","KEYS","LEAVE","LIMIT","LOCALTIME","LONG","LOOP","MEDIUMBLOB","MIDDLEINT","MOD","NOT","NUMERIC","OPTION","ORDER","OUTFILE","PROCEDURE","READS","REGEXP","REPEAT","RESTRICT","RIGHT","SCHEMAS","SENSITIVE","SHOW","SPATIAL","SQLEXCEPTION","SQL_BIG_RESULT","SSL","TABLE","TINYBLOB","TO","TRUE","UNIQUE","UPDATE","USING","UTC_TIMESTAMP","VARCHAR","WHEN","WITH","YEAR_MONTH","ALTER","AS","BEFORE","BINARY","BY","CASE","CHARACTER","COLUMN","CONTINUE","CROSS","CURRENT_TIMESTAMP","DATABASE","DAY_MICROSECOND","DEC","DEFAULT","DESC","DISTINCT","DOUBLE","EACH","ENCLOSED","EXIT","FETCH","FLOAT8","FOREIGN","GRANT",
                                                  "HIGH_PRIORITY","HOUR_SECOND","IN","INNER","INSERT","INT2","INT8","INTO","JOIN","KILL","LEFT","LINES","LOCALTIMESTAMP","LONGBLOB","LOW_PRIORITY","MEDIUMINT","MINUTE_MICROSECOND","MODIFIES","NO_WRITE_TO_BINLOG","ON","OPTIONALLY","OUT","PRECISION","PURGE","REAL","RELEASE","REPLACE","RETURN","RLIKE","SECOND_MICROSECOND","SEPARATOR","SMALLINT","SPECIFIC","SQLSTATE","SQL_CALC_FOUND_ROWS","STARTING","TERMINATED","TINYINT","TRAILING","UNDO","UNLOCK","USAGE","UTC_DATE","VALUES","VARCHARACTER","WHERE","WRITE","ZEROFILL");
    $keywords_mysql_new =    array("ASENSITIVE","CONNECTION","DECLARE","ELSEIF","GOTO","ITERATE","LOOP","READS","RETURN","SENSITIVE","SQLEXCEPTION","TRIGGER","WHILE","CALL","CONTINUE","DETERMINISTIC","EXIT","INOUT","LABEL","MODIFIES","RELEASE","SCHEMA","SPECIFIC","SQLSTATE","UNDO","CONDITION","CURSOR","EACH","FETCH","INSENSITIVE","LEAVE","OUT","REPEAT","SCHEMAS","SQL","SQLWARNING","UPGRADE");
    $keywords_mysql_allowed= array("ACTION","BIT","DATE","ENUM","NO","TEXT","TIME","TIMESTAMP");
    $word = strtoupper($word);
    if (in_array($word, $keywords_mssql))
        $err = "msSQL Keywords";
    else if (in_array($word, $keywords_odbc))
        $err = "ODBC Keywords";
    else if (in_array($word, $keywords_mssql_future))
        $err = "msSQL Future Keywords";
    else if (in_array($word, $keywords_mysql))
        $err = "mySQL Keywords";
    else if (in_array($word, $keywords_mysql_new))
        $err = "mySQL New Keywords (v.5)";
    else if (in_array($word, $keywords_mysql_allowed))
        $err = "Keywords to Avoid (Depricated)";
    else return;
    trigger_error("The identifier name you used '$word' was detected to be reserved by the list '$err'. Using that identifier should be avoided as it can break SQL queries now or in the future. Please choose another name.", \E_USER_ERROR);
}

/** Unlocks all locked tables/models. */
function unlock() {
    run("UNLOCK TABLES");
}

/**
 * Convenience function for prefixing tables.
 * @param string $table_name
 * @return string
 */
function table($table_name) {
    static $cache = array();
    if (isset($cache[$table_name]))
        return $cache[$table_name];
    // Convert backslashes to forwardslashes as backslashes can mess up queries.
    $escaped_table_name = str_replace("\\", "/", $table_name);
    return $cache[$table_name] = '`' . config\PREFIX . $escaped_table_name . '`';
}

// Import some functions to the global namespace.
include __DIR__ . "/imports.php";