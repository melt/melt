<?php namespace melt\db;

class WhereCondition {
    private $pending_field_operation = false;

    protected $where_tokens = array();

    public function getTokens(WhereCondition $additional_condition = null) {
        if ($this->pending_field_operation)
            \trigger_error(__CLASS__ . " error: Trying to read where condition tokens although query is not finished!", \E_USER_ERROR);
        $add_where_tokens =  $additional_condition === null? array(): $additional_condition->where_tokens;
        $this_is_empty = \count($this->where_tokens) == 0;
        $add_is_empty = \count($add_where_tokens) == 0;
        if ($this_is_empty && $add_is_empty)
            return array();
        else if ($add_is_empty)
            return \array_merge(array("WHERE ("), $this->where_tokens, array(")"));
        else if ($this_is_empty)
            return \array_merge(array("WHERE ("), $add_where_tokens, array(")"));
        else
            return \array_merge(array("WHERE (("), $this->where_tokens, array(") AND ("), $add_where_tokens, array("))"));
    }

    private function argToField($arg) {
        if (is_string($arg))
            $arg = new ModelField($arg);
        else if (!($arg instanceof ModelField))
            \trigger_error(__CLASS__ . " error: Unexpected argument. Expected model field!", \E_USER_ERROR);
        return $arg;
    }
    
    public function __call($name, $arguments) {
        if (!$this->applyConditionToken($name, $arguments))
            $this->tokenFail($name);
        return $this;
    }

    protected function tokenFail($name) {
        \trigger_error(__CLASS__ . " error: Condition token '$name' not understood. (Note: Tokens are case sensitive.)", \E_USER_ERROR);
    }

    protected function applyConditionToken($name, $arguments) {
        $arg = @$arguments[0];
        $not = false;
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
            case "isntIn":
                $not = true;
            case "isIn":
                $op = "IN";
                break;
            case "isntStartingWith":
            case "isntEndingWith":
            case "isntContaining":
            case "isntLike":
                $not = true;
            case "isStartingWith":
            case "isEndingWith":
            case "isContaining":
            case "isLike":
                $op = "LIKE";
                break;
            default:
                return false;
            }
            if (!$this->pending_field_operation)
                \trigger_error(__CLASS__ . " error: Invalid query operator order!", \E_USER_ERROR);
            // Add operator token, then value (or field name).
            if ($not)
                $this->where_tokens[] = "NOT";
            $this->where_tokens[] = $op;
            if ($op == "IN") {
                // Expects array or WhereCondition argument.
                if (\is_array($arg))
                    $arg = new ModelFieldValue($arg);
                else if (!($arg instanceof WhereCondition))
                    \trigger_error(__CLASS__ . " error: Unexpected argument. IN operator expects WhereCondition argument! Got: " . gettype($arg), \E_USER_ERROR);
                else if ($arg->getFromModel() === null)
                    \trigger_error(__CLASS__ . " error: Unexpected argument. WhereCondition argument for IN operator has from model missing!", \E_USER_ERROR);
                else if ($arg->getSelectFields() === null || count($arg->getSelectFields()) != 1)
                    \trigger_error(__CLASS__ . " error: Unexpected argument. WhereCondition argument for IN operator must have exactly one select field!", \E_USER_ERROR);
            } else if ($op == "LIKE") {
                if (!is_scalar($arg))
                    \trigger_error(__CLASS__ . " error: Unexpected argument. $name operator expects PHP scalar value! Got: " . gettype($arg), \E_USER_ERROR);
                if (\melt\string\ends_with($name, "StartingWith"))
                    $arg = "'" . like_pattern_strfy($arg) . "%'";
                else if (\melt\string\ends_with($name, "EndingWith"))
                    $arg = "'%" . like_pattern_strfy($arg) . "'";
                else if (\melt\string\ends_with($name, "Containing"))
                    $arg = "'%" . like_pattern_strfy($arg) . "%'";
                else
                    $arg = strfy($arg);
            } else {
                // Expects php value or model field.
                if (!($arg instanceof ModelField))
                    $arg = new ModelFieldValue($arg);
            }
            $this->pending_field_operation = false;
            // Add operator argument.
            $this->where_tokens[] = $arg;
            return true;
        }
        // Validate operator order.
        if ($this->pending_field_operation)
            \trigger_error(__CLASS__ . " error: Invalid query operator order!", \E_USER_ERROR);
        // Add welding token, and then field name.
        // Ignore welding token if nothing to weld with.
        // This enables more simple logic iteration.
        $add_where_token = count($this->where_tokens) > 0;
        if ($arg !== null) {
            // Either inner expression or field name.
            if ($arg instanceof WhereCondition) {
                if (count($arg->where_tokens) > 0) {
                    if ($add_where_token)
                        $this->where_tokens[] = $op;
                    $this->where_tokens[] = "(";
                    foreach ($arg->where_tokens as $inner_expression_query_token)
                        $this->where_tokens[] = $inner_expression_query_token;
                    $this->where_tokens[] = ")";
                }
                return true;
            } else {
                if ($add_where_token)
                    $this->where_tokens[] = $op;
                if (\is_bool($op)) {
                    $this->where_tokens[] = $op? "1": "0";
                } else {
                    $this->pending_field_operation = true;
                    $this->where_tokens[] = $this->argToField($arg);
                }
            }
        } else if ($add_where_token) {
            $this->where_tokens[] = $op;
            $this->pending_field_operation = true;
        }
        return true;
    }
}