<?php namespace melt\db;

class SelectQuery extends WhereCondition implements \IteratorAggregate, \Countable {
    private $group_by_tokens = array();
    private $order_tokens = array();
    private $groupwise_orderings = array();
    private $limit = 0;
    private $offset = 0;

    private $from_model = null;
    private $select_fields = null;
    private $is_ignoring_children = false;
    
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
        if (!\is_subclass_of($model_name, 'melt\AppModel'))
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

    public function getIsIgnoringChildren() {
        return $this->is_ignoring_children;
    }

    public function setIsIgnoringChildren($is_ignoring_children) {
        $this->is_ignoring_children = ($is_ignoring_children == true);
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

    public function hasGroupBy() {
        return \count($this->group_by_tokens) > 0;
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

    public function getGroupwiseOrderings() {
        return $this->groupwise_orderings;
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
     * Groups instances in an ordered fashion. The row selected for each
     * group will be determined by the order field and the order field sorting.
     * @param string $group_field Group to group by.
     * @param string $order_field Field to order by.
     * @param string $order ASC or DESC.
     * @see http://dev.mysql.com/doc/refman/5.5/en/example-maximum-column-group-row.html
     * @return SelectQuery
     */
    public function groupwiseOrder($group_field, $order_field, $order = "ASC") {
        if ($order != "ASC" && $order != "DESC")
            \trigger_error(__METHOD__ . " error: Unexpected \$order argument. Expected 'ASC' or 'DESC'.", \E_USER_ERROR);
        $this->groupwise_orderings[] = array($group_field, $order_field, $order === "ASC");
        return $this;
    }

    /**
     * Groups instances where the specified field have the same value and
     * returns only the first of those instances.
     * This function takes multiple fields like:
     * field1, order1, field2, order2, ...
     * @param string $field
     * @param string $order ASC or DESC.
     * @see SQL GROUP BY
     * @return SelectQuery
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
     * @return SelectQuery
     */
    public function orderBy($field, $order = "ASC") {
        $this->setOrderedField("ORDER BY", $this->order_tokens, new ModelField($field), $field, $order);
        return $this;
    }

    /**
     * Quick key lookup. This function supports limited subset of possible
     * queries (including constant field condition, order by and group by)
     * since it attempts to match a declared index with your selection.
     * An index must exists which matches the constant conditions from
     * the leftmost side and from that, keeps matching the order fields.
     * This ensures that the selection you make is optimized to take
     * constant time in respect to the table size and where the number of
     * matched rows is part of the constant.
     * Note that this function can reject combinations that would be
     * partially or even fully optimized. Customized selections can be
     * fast as well but no guarantees are made about that by this function.
     * For more information, read about MySQL index optimization and B-Trees.
     * @see http://dev.mysql.com/doc/refman/5.5/en/mysql-indexes.html
     * @param array $constants Array of fields mapped to constants.
     * Only instances which match the constants will be selected.
     * @param array $order_fields Array of fields to order
     * and possibly also group.
     * @param string $direction ASC or DESC.
     * @param boolean $group Set to true to also group by order_fields.
     * @return SelectQuery
     */
    public function byKey(array $constants = array(), array $order_fields = array(), $direction = "ASC", $group = false) {
        /*
        $from_model = $this->from_model;
        if (\count($arguments) < 2)
            \trigger_error("key() expects at least two arguments.", \E_USER_ERROR);
        else if ((\count($arguments) % 2) != 0)
            \trigger_error("key() expects a even number of arguments.", \E_USER_ERROR);
        \reset($arguments);*/
        $keys = array();
        $note_key_fn = function($key) use (&$keys) {
            $key = trim_id($key);
            if (\array_key_exists($key, $keys))
                \trigger_error("Cannot use key more than once!", \E_USER_ERROR);
            $keys[$key] = 1;
            return $key;
        };
        $subresolve = false;
        $constant_keys = array();
        if (\count($constants) > 0) {
            $where_constant = new WhereCondition();
            foreach ($constants as $key => $constant) {
                $key = $note_key_fn($key);
                $constant_keys[] = $key;
                $pos = \strrpos($key, "->");
                if ($pos !== false) {
                    $this_subresolve = \substr($key, 0, $pos);
                    if ($subresolve === false) {
                        $subresolve = $this_subresolve;
                    } else if ($this_subresolve !== $subresolve) {
                        \trigger_error("Using different arrow fields for constant constraints!"
                        . " All constant constraints must be resolved the same way.", \E_USER_ERROR);
                    }
                } else if ($subresolve === false)
                    $subresolve === null;
                $where_constant->and($key)->is($constant);
            }
            $this->and($where_constant);
        }
        if ($subresolve === false)
            $subresolve = null;
        if (\count($order_fields) > 0 && $subresolve !== null)
            \trigger_error("Using arrow fields for constant constraints is not compatible with also supplying order/group fields.", \E_USER_ERROR);
        $order_keys = array();
        foreach ($order_fields as $field) {
            $field = $note_key_fn($field);
            $order_keys[] = $field;
            $this->orderBy($field, $direction);
            if ($group)
                $this->groupBy($field, $direction);
        }
        $this->where_tokens[] = new IndexRequirement($constant_keys, $order_keys, $subresolve);
        return $this;
    }

    /**
     * Orders the result randomly.
     * @see orderBy
     * @return SelectQuery
     */
    public function orderRandomly() {
        $this->setOrderedField("ORDER BY", $this->order_tokens, "RAND()", "__rand", "ASC");
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
     * @return SelectQuery
     */
    public function forUpdate() {
        if ($this->is_for_update)
            return;
        $this->resetInternalResult();
        $this->is_for_update = true;
        return $this;
    }
    
    /**
     * Negates the effects of ->forUpdate() but only if the actual data has
     * not been selected from the database yet.
     * @return SelectQuery
     */
    public function forRead() {
        $this->is_for_update = false;
        return $this;
    }

    /**
     * Limits result by the specified number.
     * @param integer $limit
     * @see SQL LIMIT
     * @return SelectQuery
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
     * @return SelectQuery
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
     * @return integer
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
     * Limits the selection to one row
     * and returns true if the count is non-zero.
     * @return integer
     */
    public function exists() {
        return $this->limit(1)->count() > 0;
    }

    /**
     * Returns the first instance of this selection or NULL if
     * selection is empty.
     * @return \melt\Model
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
     * Returns all instances of this selection in an array of \melt\Model's
     * @return array[\melt\Model]
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