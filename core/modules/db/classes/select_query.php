<?php namespace nmvc\db;

class SelectQuery extends WhereCondition implements \IteratorAggregate, \Countable {
    private $group_by_tokens = array();
    private $order_tokens = array();
    private $limit = 0;
    private $offset = 0;

    private $from_model = null;
    private $select_fields = null;
    
    private $is_counting = false;
    private $is_calc_found_rows = false;
    private $is_for_update = false;

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

    public function __sleep() {
        return \array_diff(
            \array_keys(\get_class_vars(__CLASS__)),
            array(
                "is_calc_found_rows",
                "is_counting",
                "internal_result_cache",
                "internal_result_count_cache",
                "internal_found_rows_count_cache",
                "internal_first_result_cache",
            )
        );
    }

    private function refreshInternalResult() {
        $from_model = $this->from_model;
        if ($from_model === null)
            trigger_error(__CLASS__ . " error: Cannot get result of selection without specifying from model!", \E_USER_ERROR);
        if ($this->is_counting) {
            // Only counting.
            $result = $from_model::getInstancesForSelection($this);
            $this->internal_result_count_cache = $result;
        } else {
            // Full result.
            $this->internal_result_cache = $from_model::getInstancesForSelection($this);
            $this->internal_result_count_cache = count($this->internal_result_cache);
        }
        if ($this->is_calc_found_rows) {
            $found_rows_result = next_array(query("SELECT FOUND_ROWS()"));
            $this->internal_found_rows_count_cache = intval($found_rows_result[0]);
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
        }
        $this->select_fields = $select_fields;
    }

    public function getIsCounting() {
        return $this->is_counting;
    }

    public function getIsCalcFoundRows() {
        return $this->is_calc_found_rows;
    }

    public function getIsForUpdate() {
        return $this->is_for_update;
    }

    private function setOrderedField($operator, &$tokens_array, $field, $field_id, $order) {
        $field_key = "f$field_id";
        $order_key = "o$field_id";
        $adding = !\array_key_exists($field_key, $tokens_array);
        if ($adding) {
            if (\count($tokens_array) > 0)
                $tokens_array[] = ",";
            else
                $tokens_array[] = $operator;
        }
        $tokens_array[$field_key] = $field;
        $order = \strtoupper($order);
        if ($order != "ASC" && $order != "DESC")
            \trigger_error(__METHOD__ . " error: Unexpected \$order argument. Expected 'ASC' or 'DESC'.", \E_USER_ERROR);
        // Modification of query requires flushing internal cache.
        if ($adding || $tokens_array[$order_key] != $order)
            $this->resetInternalResult();
        $tokens_array[$order_key] = $order;
        return;

    }

    public function getTokens(WhereCondition $additional_condition = null) {
        $tokens = array_merge(parent::getTokens($additional_condition), $this->group_by_tokens, $this->order_tokens);
        if ($this->offset > 0 || $this->limit > 0) {
            $tokens[] = "LIMIT";
            $tokens[] = (string) $this->offset;
            $tokens[] = ",";
            $tokens[] = $this->limit > 0? (string) $this->limit: "18446744073709551615";
        }
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
    public function groupBy($field, $order = "ASC") {
        $this->setOrderedField("GROUP BY", $this->group_by_tokens, new ModelField($field), $field, $order);
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
        $this->setOrderedField("ORDER BY", $this->order_tokens, new ModelField($field), $field, $order);
        return $this;
    }

    /**
     * Orders the result randomly.
     * @see orderBy
     */
    public function orderRandomly() {
        $this->setOrderedField("ORDER BY", $this->order_tokens, "RAND()", "__rand", $order);
        return $this;
    }

    /**
     * This statement should be added to selections of instances that will
     * later be modified. If forUpdate is not used in such selections,
     * deadlocks could occour in rare cases.
     * This function also limits the asynchronicity of the
     * selection, so it should only be used for selections of instances that
     * will later be stored/unlinked.
     * @see http://dev.mysql.com/doc/refman/5.1/en/innodb-locking-reads.html
     */
    public function forUpdate() {
        if ($this->is_for_update)
            return;
        $this->resetInternalResult();
        $this->is_for_update = true;
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

    /**
     * Tells the database to calculate how many rows there would be in the
     * result set, disregarding any LIMIT clause.
     */
    public function countFoundRows() {
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

    public function __call($name, $arguments) {
        if ($this->applyConditionToken($name, $arguments)) {
            // Condition token applied, need to refresh internal result.
            $this->resetInternalResult();
            return $this;
        }
        // Attempting to apply function on entire result set?
        $from_model = $this->from_model;
        if ($from_model !== null && \is_callable(array($from_model, $name))) {
            $all = $this->all();
            $ret = array();
            foreach ($all as $instance)
                $ret[] = \call_user_func_array(array($instance, $name), $arguments);
            return $ret;
        }
        $this->tokenFail($name);
    }
}