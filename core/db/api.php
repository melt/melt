<?php namespace melt\db;

/**
 * @desc Calling this function enables buffer printing of all SQL queries.
 *       Useful for SQL batch scripts.
 */
function enable_display() {
    \melt\request\reset();
    \header('Content-Type: text/plain');
    \define("OUTPUT_MYSQL_QUERIES", true);
    \ob_end_clean();
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
 * @example Input: a 'test' Output: 'a \'test\''
 * @param string $string
 * @param integer $max_length Maximum length of string in charachers.
 * Only use this parameter if string contains UTF-8 text and not binary data.
 * @return string The escaped and quoted string you inputed.
 */
function strfy($string, $max_length = null) {
    if ($max_length !== null && $max_length >= 0)
        $string = \iconv_substr($string, 0, $max_length);
    return "'" . get_link()->real_escape_string($string) . "'";
}

/**
 * Will strfy escape any charachers that can be used to break the query
 * without quoting the string automaticlly. In addition,
 * it will escape any charachers that has special meaning when combined
 * with the mySQL LIKE operator, turning the string into a LIKE pattern.
 * @param string $string The string to convert to a LIKE pattern.
 * @return string String ready to be used as a single matching block in LIKE pattern.
 */
function like_pattern_strfy($string, $max_length = null) {
    if ($max_length !== null && $max_length >= 0)
        $string = \iconv_substr($string, 0, $max_length);
    $string = get_link()->real_escape_string($string);
    return \str_replace(array('%', '_'), array('\%', '\_'), $string);
}

/**
 * Takes a PHP value and converts it to a SQL query value token.
 * @param mixed $php_value
 * @return string SQL query value token, ready for concatenation.
 */
function php_value_to_sql($php_value) {
    if (\is_null($php_value))
        return "0";
    else if (!\is_scalar($php_value)) {
        \trigger_error("Cannot represent non scalar values in sql!", \E_USER_WARNING);
        return "0";
    } else if (\is_string($php_value))
        return strfy($php_value);
    else if (\is_bool($php_value))
        return $php_value? "1": "0";
    else
        return (string) $php_value;
}

/**
 * Returns the configured storage engine (in lowercase) that is expected
 * to be configured for all tables (and will be configured during sync).
 * It can also return NULL which means that Melt Framework will not autoconfigure
 * any storage engines.
 * @return string
 */
function storage_engine() {
    $configured_engine = \strtolower(config\STORAGE_ENGINE);
    $possible_options = array("custom" => 1, "auto" => 1, "myisam" => 1, "innodb" => 1);
    if (!isset($configured_engine[$possible_options]))
        \trigger_error("Invalid configuration 'db\config\STORAGE_ENGINE', expects one of: " . \array_keys($possible_options), \E_USER_ERROR);
    if ($configured_engine == "custom")
        return null;
    else if ($configured_engine == "auto")
        $configured_engine = config\REQUEST_LEVEL_TRANSACTIONALITY? "innodb": "myisam";
    return $configured_engine;
}

/**
 * Queries the database, and throws specified error on failure.
 * It will throw an exception if query fails.
 * @param string $query The SQL query.
 * @see To query without errorhandling, use db\run().
 * @return mixed Returns a result resource handle on success, TRUE if no rows were returned, or FALSE on error.
 */
function query($query) {
    $result = run($query);
    if ($result === FALSE) {
        $err = get_link()->error;
        if (\strlen($query) > 512)
            $query = \substr($query, 0, 512);
        $query = \var_export($query, true);
        $errmsg = "A MySQL query failed: {\n\t".$query."\n}\nSQL error: ".$err;
        if (!\defined("OUTPUT_MYSQL_QUERIES"))
            \trigger_error($errmsg, \E_USER_ERROR);
        else
            echo $errmsg;
        exit;
    }
    return $result;
}

/**
 * Runs a query on the database, ignoring any failure.
 * @param string $query The SQL query to execute on acms DB connection.
 * @see To query with errorhandling, use db\query().
 * @return mixed A result resource handle on success, TRUE if no rows were returned, or FALSE on error.
 */
function run($query) {
    if ($query == "")
        return true;
    if (\defined("OUTPUT_MYSQL_QUERIES")) {
        echo $query . "\r\n";
        \ob_flush();
    }
    total_queries(true);
    if (!config\DEBUG_QUERY_BENCHMARK)
        return get_link()->query($query);
    // Benchmark query and log.
    $time_start = microtime(true);
    $ret = get_link()->query($query);
    $total = \microtime(true) - $time_start;
    $debug_info = \date("r") . ": $query\n^-" . \round($total * 1000, 1) . " ms\ncaller: " . \melt\internal\get_user_callpoint() . "\n\n";
    \file_put_contents(APP_DIR . "/db_debug_query_benchmark.log", $debug_info, FILE_APPEND);
    return $ret;
}

/**
 * Returns the total number of queries that has been run during this request.
 * @return int 
 */
function total_queries() {
    static $total_queries = 0;
    if (func_num_args() > 0 && func_get_arg(0) === true)
        $total_queries++;
    return $total_queries;
}

/**
 * Returns the database backend link.
 * @return \mysqli
 */
function get_link() {
    static $mysqli = null;
    if ($mysqli !== null)
        return $mysqli;
    // Make sure mysql extention is loaded.
    if (!\extension_loaded("mysqli"))
        \trigger_error("Error: The MySQLi extention is not loaded. Your PHP installation is not compatible with Melt Framework!", \E_USER_ERROR);
    // Can spend 10 seconds max connecting or half the request time limit.
    // This allows graceful handling of timeouts.
    $max_mysql_timeout = \intval(\ini_get("max_execution_time"))  / 2;
    if ($max_mysql_timeout > 10)
        $max_mysql_timeout = 10;
    // Use specified database.
    if (\strlen(config\NAME) == 0)
        trigger_error("No database name specified in configuration! This is required.", \E_USER_ERROR);
    // Connect to the database.
    $mysqli = \mysqli_init();
    $mysqli->options(\MYSQLI_OPT_CONNECT_TIMEOUT, $max_mysql_timeout);
    $connected = $mysqli->real_connect(config\HOST, config\USER, config\PASSWORD, config\NAME, \intval(config\PORT));
    if (!$connected)
        \trigger_error("The mySQLi connection could not be established. " . mysqli_error(), \E_USER_ERROR);
    $mysqli->set_charset('utf8');
    if (config\REQUEST_LEVEL_TRANSACTIONALITY) {
        // Applying per-request transactionality. Rolling back any previous
        // uncommited changes on this transaction and starting a new.
        $mysqli->query("ROLLBACK");
        $mysqli->query("SET autocommit = 0");
        $mysqli->query("START TRANSACTION WITH CONSISTENT SNAPSHOT;");
    }
    return $mysqli;
}

/**
 * Returns the number of affected rows in the last query.
 * @return integer
 */
function affected_rows() {
    return get_link()->affected_rows;
}

/**
 * Number of rows in result.
 * @return integer
 */
function get_num_rows(\mysqli_result $result) {
    return $result->num_rows;
}

/**
 * Number of columns in result.
 * @return integer
 */
function get_num_cols(\mysqli_result $result) {
    return $result->field_count;
}

/** Returns the next row in result as an associative array,
 * or FALSE if there are no more rows.
 * @return array
 */
function next_assoc(\mysqli_result $result) {
    $row = $result->fetch_assoc();
    return \is_array($row)? $row: false;
}

/**
 * Returns the next row in result as a numeric array,
 * or FALSE if there are no more rows.
 * @return array
 */
function next_array(\mysqli_result $result) {
    $row = $result->fetch_row();
    return \is_array($row)? $row: false;
}

/**
 * Seeks the result position to row n.
 * Returns true if successful.
 * @return boolean
 */
function data_seek(\mysqli_result $result, $n) {
    return $result->data_seek($n);
}

/**
* Returns false if the current column is a subset of the specified column.
* If current is int(9) or int(123) and specified is int, this returns false.
* However, if current is int(23) and specified is int(12), this returns true.
 * @return boolean
*/
function sql_column_need_update($specified, $current) {
    $lengthy_pattern = '#(\w+)\s*\((\d+)\)#';
    $specified = \preg_replace('#\s+#', '', $specified);
    $current = \preg_replace('#\s+#', '', $current);
    $specified_is_lengthy = (1 == \preg_match($lengthy_pattern, $specified, $lengthy_specified));
    $current_is_lengthy = (1 == \preg_match($lengthy_pattern, $current, $lengthy_current));
    if (!$current_is_lengthy && !$specified_is_lengthy) {
        // Needs update if they differ.
        return \strcasecmp($specified, $current) !== 0;
    } else if ($current_is_lengthy && !$specified_is_lengthy) {
        // Needs update if type differ.
        return \strcasecmp($lengthy_current[1], $specified) !== 0;
    } else if (!$current_is_lengthy && $specified_is_lengthy) {
        // Needs update (specified has length).
        return true;
    } else {
        // Needs update if length differs.
        $current_length = \intval($lengthy_current[2]);
        $specified_length = \intval($lengthy_specified[2]);
        if ($current_length !== $specified_length)
            return true;
        // Needs update if type differs.
        return \strcasecmp($lengthy_current[1], $lengthy_specified[1]) !== 0;
    }
}


/** Returns all tables in the database. */
function get_all_tables() {
    // Gets all tables in the database.
    static $all_tables = null;
    if ($all_tables === null) {
        $all_tables = array();
        $all_tables_query = query("SHOW TABLES");
        while (false !== ($table = next_array($all_tables_query)))
            $all_tables[] = \strtolower($table[0]);
    }
    return $all_tables;
}

/**
* Syncronizes a table in the database with
* the generic table model used by Melt Framework.
* @paam string $table_name The raw table name, the identifier without prefixing.
* @param $parsed_col_array array Parsed column array of model.
*/
function sync_table_layout_with_model($table_name, array $parsed_col_array, array $expected_indexes) {
    // Make an array where [name] => sql_type
    $column_types = array();
    // Check names and fetches types.
    foreach ($parsed_col_array as $name => $column) {
        if ($column->storage_type == VOLATILE)
            continue;
        verify_keyword($name);
        $column_types[\strtolower($name)] = $column->getSQLType();
    }
    $all_tables = get_all_tables();
    if (\in_array(\substr(table($table_name), 1, -1), $all_tables)) {
        // Altering existing table.
        $current_columns = query("DESCRIBE " . table($table_name));
        while (false !== ($index = next_array($current_columns))) {
            $current_name = \strtolower($index[0]);
            $current_type = \strtolower($index[1]);
            $supports_null = \strcasecmp($index[2], "no") != 0;
            // ID column is special case.
            if ($current_name == 'id') {
                if (!\melt\string\starts_with($current_type, "bigint"))
                    query("ALTER TABLE " . table($table_name) . " MODIFY COLUMN id bigint NOT NULL");
                continue;
            }
            // Skip unknown columns.
            if (!isset($column_types[$current_name]))
                continue;
            $expected_type = $column_types[$current_name];
            if ($supports_null || isset($column_types[$current_name]) && sql_column_need_update($expected_type, $current_type)) {
                // Invalid datatype, alter it.
                query("ALTER TABLE " . table($table_name) . " MODIFY COLUMN $current_name $expected_type NOT NULL");
            }
            // This column was confirmed.
            unset($column_types[$current_name]);
        }
        // Insert the rest of the columns.
        $adds = array();
        foreach ($column_types as $name => $type)
            $adds[] = "$name $type NOT NULL";
        $adds = implode(", ", $adds);
        if (strlen($adds) > 0)
            query("ALTER TABLE " . table($table_name) . " ADD ($adds)");
    } else {
        // Creating new table.
        $adds = 'id BIGINT UNSIGNED NOT NULL, PRIMARY KEY (id)';
        foreach ($column_types as $name => $type)
            $adds .= ", $name $type NOT NULL";
        query("CREATE TABLE " . table($table_name) . " ( $adds )");
    }
    // Scan and update indexes.
    $show_indexes = query("SHOW INDEXES IN " . table($table_name));
    $current_indexes = array();
    while (false !== ($index = next_assoc($show_indexes))) {
        $key_name = $index["Key_name"];
        if (!isset($current_indexes[$key_name])) {
            $current_indexes[$key_name] = array(
                "columns" => array($index["Column_name"]),
                "is_unique" => $index["Non_unique"] == 0,
            );
        } else {
            $current_indexes[$key_name]["columns"][] = $index["Column_name"];
        }
    }
    foreach ($current_indexes as $key_name => $current_index) {
        // Temporary check that removes indexes from previous framework.
        if (!\melt\string\starts_with($key_name, '_nmvc_')) {
            $melt_index = \melt\string\starts_with($key_name, '_melt_');
            if (!$melt_index)
                continue;
            $unique = $index["Non_unique"] == 0;
            // Compare index and see if it's still valid.
            if (isset($expected_indexes[$key_name])
            && $current_index["is_unique"] == $expected_indexes[$key_name]["is_unique"]) {
                $expected_index =& $expected_indexes[$key_name];
                \sort($expected_index["columns"]);
                \sort($current_index["columns"]);
                if ($expected_index["columns"] == $current_index["columns"]) {
                    // Index is correct.
                    unset($expected_indexes[$key_name]);
                    continue;
                }
            }
        }
        // Index is wrong, drop it.
        query("DROP INDEX $key_name ON " . table($table_name));
    }
    // Add all expected indexes that wasn't added.
    foreach ($expected_indexes as $key_name => $index) {
        if ($key_name === "PRIMARY")
            continue;
        $unique = $index["is_unique"]? " UNIQUE": "";
        $columns = \implode(",", $index["columns"]);
        query("CREATE$unique INDEX $key_name ON " . table($table_name) . "(" . $columns . ")");
    }
    // Finally reset the auto id trigger.
    $trigger = table("aid_trigger_" . $table_name);
    run("DROP TRIGGER " . $trigger);
    if (!config\USE_TRIGGER_SEQUENCING)
        return;
    query(
        "CREATE TRIGGER " . $trigger . " BEFORE INSERT ON " . table($table_name)
        . " FOR EACH ROW BEGIN UPDATE " . table('core__seq') . " SET id = "
        . " LAST_INSERT_ID(id+1); SET @last_insert = LAST_INSERT_ID(); SET "
        . " NEW.id = @last_insert; END;",
        "Adding trigger failed. Probably due to lack of TRIGGER permission or "
        . "old mySQL version. Either GRANT TRIGGER permissions to current user "
        . "or set USE_TRIGGER_SEQUENCING to false in config.php to use a slower"
        . "method that doesn't protect against data corruption from to "
        . "duplicate table spanning ID's (by other mySQL software)."
    );
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
    $word = \strtoupper($word);
    if (\in_array($word, $keywords_mssql))
        $err = "msSQL Keywords";
    else if (\in_array($word, $keywords_odbc))
        $err = "ODBC Keywords";
    else if (\in_array($word, $keywords_mssql_future))
        $err = "msSQL Future Keywords";
    else if (\in_array($word, $keywords_mysql))
        $err = "mySQL Keywords";
    else if (\in_array($word, $keywords_mysql_new))
        $err = "mySQL New Keywords (v.5)";
    else if (\in_array($word, $keywords_mysql_allowed))
        $err = "Keywords to Avoid (Depricated)";
    else return;
    \trigger_error("The identifier name you used '$word' was detected to be reserved by the list '$err'. Using that identifier should be avoided as it can break SQL queries now or in the future. Please choose another name.", \E_USER_ERROR);
}

/**
 * Enters a critical section identified by the specified named lock.
 * If timeout is not specified, the program will wait indefinetly
 * for an unlock and then return true.
 * If timeout is specified, the program will wait a maximum of $timeout
 * seconds and then return false if the named lock was not unlocked during
 * the period.
 * @return boolean
 */
function enter_critical_section($named_lock, $timeout = null) {
    $db_lock = strfy(config\NAME . "_" . config\PREFIX . $named_lock);
    $db_timeout = $timeout === null? 0: \intval($timeout);
    do {
        $r = query("SELECT GET_LOCK($db_lock, $db_timeout)");
        $r = next_array($r);
        $r = $r[0];
        if ($r == 0) {
            if ($timeout !== null)
                return false;
            else
                \usleep(25000);
        }
    } while ($r == 0);
    return true;
}

/**
 * Exits critical section by releasing the named lock.
 * @return void
 */
function exit_critical_section($named_lock) {
    $db_lock = strfy(config\NAME . "_" . config\PREFIX . $named_lock);
    query("SELECT RELEASE_LOCK($db_lock)");
}

/**
 * Convenience function for prefixing and quoting table names
 * when building queries.
 * @param string $table_name
 * @return string
 */
function table($table_name) {
    static $cache = array();
    if (isset($cache[$table_name]))
        return $cache[$table_name];
    return $cache[$table_name] = '`' . \strtolower(config\PREFIX . $table_name) . '`';
}

/**
 * Starts building an inner selection query expression.
 * @param string $first_field The first field name in the selector.
 * @return melt\db\WhereCondition
 */
function expr($first_field = null) {
    $wc = new \melt\db\WhereCondition();
    if ($first_field !== null)
        $wc->where($first_field);
    return $wc;
}

/**
 * Signifies a field in a selection query.
 * @param string $field_name
 * @return \melt\db\ModelField
 */
function field($field_name) {
    return new \melt\db\ModelField($field_name);
}

/**
 * Trims away any _id from the end of a column name.
 * @param string $column_name
 * @return string
 */
function trim_id($column_name) {
    return \melt\string\ends_with($column_name, "_id")? \substr($column_name, 0, -3): $column_name;
}


/**
 * Converts an ordered key set (array of columns) to an index identifier.
 * @param string $key_set
 * @return \melt\db\FieldSet
 */
function key_set_to_index_id(array $key_set) {
    $key_set_size = \count($key_set);
    if ($key_set_size == 1) {
        $key_set = trim_id(\reset($key_set));
    } else {
        foreach ($key_set as &$key)
            $key = trim_id($key);
        $key_set = \implode(",", $key_set);
    }
    return "_melt_" . \substr(\sha1($key_set), 1, 12);
}
