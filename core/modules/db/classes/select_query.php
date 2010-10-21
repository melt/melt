<?php namespace nmvc\db;

class SelectQuery implements \IteratorAggregate, \Countable {
    private $pending_field_operation = false;

    private $where_tokens = array();
    private $group_by_tokens = array();
    private $order_tokens = array();
    private $limit = 0;
    private $offset = 0;

    private $from_model = null;
    private $select_fields = null;
    
    private $is_counting = false;
    private $is_calc_found_rows = false;

    private $internal_result_cache = null;
    private $internal_result_count_cache = null;
    private $internal_found_rows_count_cache = null;
    private $internal_first_result_cache = false;

    private function resetInternalResult() {
        $this->internal_first_result_cache = false;
        $this->internal_result_cache = null;
        $this->internal_result_count_cache = null;
        $this->internal_found_rows_count_cache = null;
    }

    private function refreshInternalResult() {
        $from_model = $this->from_model;
        if ($from_model === null)
            trigger_error(__CLASS__ . " error: Cannot get result of selection without specifying from model!", \E_USER_ERROR);
        if ($this->is_counting) {
            // Only counting.
            $result = $from_model::getDataForSelection($this);
            $this->internal_result_count_cache = $result;
        } else {
            // Full result.
            $this->internal_result_cache = $from_model::getInstancesForSelection($this);
            $this->internal_result_count_cache = count($this->internal_result_cache);
        }
        if ($this->is_calc_found_rows) {
            $found_rows_result = db\next_array(db\query("SELECT FOUND_ROWS()"));
            $this->is_calc_found_rows = intval($found_rows_result[0]);
        }
    }

    private function validateModelName($model_name) {
        if ($model_name === null)
            return;
        if (!\is_subclass_of($model_name, 'nmvc\AppModel'))
            trigger_error("Cannot represent non scalar values in sql!", \E_USER_ERROR);
    }
    
    public function __construct($from_model = null, $select_fields = null) {
        $this->setFromModel($from_model);
        $this->setSelectFields($select_fields);
    }

    public function getFromModel() {
        return $this->from_model;
    }

    public function setFromModel($from_model) {
        $this->validateModelName($from_model);
        $this->from_model = $from_model;
    }

    public function getSelectFields() {
        return $this->select_fields;
    }

    public function setSelectFields($select_fields) {
        if ($select_fields !== null) {
            // Validate fields.
            if (!is_array($select_fields) || count($select_fields) == 0)
                trigger_error("Expected argument 1 to be array with at least one item.", \E_USER_ERROR);
            $from_model = $this->from_model;
            if ($from_model === null)
                trigger_error("Cannot set select fields to non null without specifying from model.", \E_USER_ERROR);
            foreach ($select_fields as $field)
                $from_model::translateFieldToColumn($field, true);
        }
        $this->select_fields = $select_fields;
    }

    public function getIsCounting() {
        return $this->is_counting;
    }

    public function getIsCalcFoundRows() {
        return $this->is_calc_found_rows;
    }

    private function argToField($arg) {
        if (is_string($arg))
            $arg = new ModelField($arg);
        else if (!($arg instanceof ModelField))
            trigger_error(__CLASS__ . " error: Unexpected argument. Expected model field!", \E_USER_ERROR);
        return $arg;
    }

    private function setOrderedField($operator, &$tokens_array, $field, $order) {
        $field_key = "f$field";
        $order_key = "o$field";
        $adding = !\array_key_exists($field_key, $tokens_array);
        if ($adding) {
            if (\count($tokens_array) > 0)
                $tokens_array[] = ",";
            else
                $tokens_array[] = $operator;
        }
        $tokens_array[$field_key] = new ModelField($field);
        $order = \strtoupper($order);
        if ($order != "ASC" && $order != "DESC")
            \trigger_error(__METHOD__ . " error: Unexpected \$order argument. Expected 'ASC' or 'DESC'.", \E_USER_ERROR);
        // Modification of query requires flushing internal cache.
        if ($adding || $tokens_array[$order_key] != $order)
            $this->resetInternalResult();
        $tokens_array[$order_key] = $order;
        return;

    }

    public function getSelectSQLTokens() {
        if ($this->pending_field_operation)
            trigger_error(__CLASS__ . " error: Trying to read WHERE tokens although query is not finished!", \E_USER_ERROR);
        $tokens = array_merge($this->where_tokens, $this->group_by_tokens, $this->order_tokens);
        if ($this->limit > 0)
            $tokens[] = " LIMIT " . $this->offset . "," . $this->limit;
        else if ($this->offset > 0)
            $tokens[] = " LIMIT " . $this->offset . ",18446744073709551615";
        return $tokens;
    }

    /**
     * Groups instances where the specified field have the same value and
     * returns only the first of those instances.
     * This function takes multiple fields like:
     * field1, order1, field2, order2, ...
     * @param string $field
     * @param string $order ASC or DESC.
     * @see SQL GROUP BY
     */
    public function groupBy($field, $order) {
        $this->setOrderedField("GROUP BY", $this->group_by_tokens, $field, $order);
        return $this;
    }

    /**
     * Orders result by the specified field and in the specified order.
     * This function takes multiple fields like:
     * field1, order1, field2, order2, ...
     * @param string $field
     * @param string $order ASC or DESC.
     * @see SQL ORDER BY
     */
    public function orderBy($field, $order = "ASC") {
        $this->setOrderedField("ORDER BY", $this->order_tokens, $field, $order);
        return $this;
    }

    /**
     * Limits result by the specified number.
     * @param integer $limit
     * @see SQL LIMIT
     */
    public function limit($limit) {
        $limit = intval($limit);
        if ($limit < 0) {
            \trigger_error(__METHOD__ . " error: Unexpected \$limit argument. Must be integer larger than zero.", \E_USER_WARNING);
            return $this;
        }
        if ($this->limit == $limit)
            return $this;
        $this->resetInternalResult();
        $this->limit = $limit;
        return $this;
    }

    /**
     * Offsets result by the specified number.
     * @param integer $offset
     * @see SQL OFFSET
     */
    public function offset($offset) {
        $offset = intval($offset);
        if ($offset < 0) {
            \trigger_error(__METHOD__ . " error: Unexpected \$offset argument. Must be integer larger than zero.", \E_USER_WARNING);
            return $this;
        }
        if ($this->offset == $offset)
            return $this;
        $this->resetInternalResult();
        $this->offset = $offset;
        return $this;
    }

    public function __call($name, $arguments) {
        // Find query token.
        switch ($name) {
        // Welding tokens.
        case "where":
        case "and":
            $op = "AND";
            break;
        case "or":
            $op = "OR";
            break;
        default:
            switch ($name) {
            // Comparision tokens.
            case "is":
                $op = "=";
                break;
            case "isnt":
                $op = "!=";
                break;
            case "isLessThan":
                $op = "<";
                break;
            case "isMoreThan":
                $op = ">";
                break;
            case "isntMoreThan":
                $op = "<=";
                break;
            case "isntLessThan":
                $op = ">=";
                break;
            case "in":
                $op = "IN";
                break;
            case "startsWith":
                $op = "LIKE";
                break;
            case "endsWith":
                $op = "LIKE";
                break;
            case "contains":
                $op = "LIKE";
                break;
            case "like":
                $op = "LIKE";
                break;
            default:
                // Attempting to apply function on entire result set?
                $from_model = $this->from_model;
                if ($from_model !== null && \is_callable(array($from_model, $name))) {
                    $all = $this->all();
                    $ret = array();
                    foreach ($all as $instance)
                        $ret[] = \call_user_func_array(array($instance, $name), $arguments);
                    return $ret;
                }
                trigger_error(__CLASS__ . " error: Operator/token/function '" . \func_get_arg(0) . "' not supported/understood/accessible. (Note: Tokens are case sensitive.)", \E_USER_ERROR);
                break;
            }
            // Modification of query requires flushing internal cache.
            $this->resetInternalResult();
            $arg = @$arguments[0];
            if (!$this->pending_field_operation)
                trigger_error(__CLASS__ . " error: Invalid query operator order!", \E_USER_ERROR);
            // Add operator token, then value (or field name).
            $this->where_tokens[] = $op;
            if ($op == "IN") {
                // Expects SelectQuery argument.
                if (!($arg instanceof SelectQuery))
                    trigger_error(__CLASS__ . " error: Unexpected argument. IN operator expects SelectQuery argument! Got: " . gettype($arg), \E_USER_ERROR);
                if ($arg->getFromModel() === null)
                    trigger_error(__CLASS__ . " error: Unexpected argument. SelectQuery argument for IN operator has from model missing!", \E_USER_ERROR);
                if ($arg->getWhereTokens() === null || count($arg->getWhereTokens()) != 1)
                    trigger_error(__CLASS__ . " error: Unexpected argument. SelectQuery argument for IN operator must have exactly one select field!", \E_USER_ERROR);
            } else if ($op == "LIKE") {
                if (!is_scalar($arg))
                     trigger_error(__CLASS__ . " error: Unexpected argument. $name operator expects PHP scalar value! Got: " . gettype($arg), \E_USER_ERROR);
                if ($name == "startsWith")
                    $pattern = "'" . like_pattern_strfy($arg) . "%'";
                else if ($name == "endsWith")
                    $pattern = "'%" . like_pattern_strfy($arg) . "'";
                else if ($name == "contains")
                    $pattern = "'%" . like_pattern_strfy($arg) . "%'";
                else
                    $pattern = strfy($arg);
                $this->where_tokens[] = $pattern;
            } else {
                // Expects php value or model field.
                if (!($arg instanceof ModelField))
                    $arg = new ModelFieldValue($arg);
            }
            $this->pending_field_operation = false;
            // Add operator argument.
            $this->where_tokens[] = $arg;
            return $this;
        }
        // Modification of query requires flushing internal cache.
        $this->resetInternalResult();
        $arg = @$arguments[0];
        // Validate operator order.
        if ($this->pending_field_operation)
            trigger_error(__CLASS__ . " error: Invalid query operator order!", \E_USER_ERROR);
        // Add welding token, and then field name.
        // Ignore welding token if nothing to weld with. 
        // This enables more simple logic iteration.
        $where_initialized = count($this->where_tokens) > 0;
        if (!$where_initialized)
            $this->where_tokens[] = "WHERE";
        else
            $this->where_tokens[] = $op;
        if ($arg !== null) {
            // Either inner expression or field name.
            if ($arg instanceof SelectQuery) {
                if (count($arg->where_tokens) > 0) {
                    $this->where_tokens[] = "(";
                    foreach ($arg->where_tokens as $inner_expression_query_token)
                        $this->where_tokens[] = $inner_expression_query_token;
                    $this->where_tokens[] = ")";
                }
                return $this;
            } else {
                $this->where_tokens[] = $this->argToField($arg);
            }
            $this->pending_field_operation = true;
        }
        return $this;
    }

    /**
     * Tells the database to calculate how many rows there would be in the
     * result set, disregarding any LIMIT clause.
     */
    public function count_found_rows() {
        if ($this->internal_found_rows_count_cache === null) {
            $this->is_calc_found_rows = true;
            $this->refreshInternalResult();
            $this->is_calc_found_rows = false;
        }
        return $this->internal_found_rows_count_cache;
    }

    /**
     * Returns the count of this selection.
     * @return integer
     */
    public function count() {
        if ($this->internal_result_count_cache === null) {
            $this->is_counting = true;
            $this->refreshInternalResult();
            $this->is_counting = false;
        }
        return $this->internal_result_count_cache;
    }

    /**
     * Returns the first instance of this selection or NULL if
     * selection is empty.
     * @return \nmvc\Model
     */
    public function first() {
        if ($this->internal_first_result_cache !== false)
            return $this->internal_first_result_cache;
        else if ($this->internal_result_cache !== null) {
            $this->internal_first_result_cache = \count($this->internal_result_cache) == 0? null: \reset($this->internal_result_cache);
            return $this->internal_first_result_cache;
        }
        $limit_stack = $this->limit;
        $this->limit = 1;
        $this->refreshInternalResult();
        $first = $this->first();
        $this->internal_result_cache = null;
        $this->limit = $limit_stack;
        return $first;
    }

    /**
     * Returns all instances of this selection in an array of \nmvc\Model's
     * @return array[\nmvc\Model]
     */
    public function all() {
        if ($this->internal_result_cache === null)
            $this->refreshInternalResult();
        return $this->internal_result_cache;
    }

    public function getIterator() {
        return new \ArrayIterator($this->all());
    }
}