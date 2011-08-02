<?php namespace melt\db;

class InjectedCondition extends WhereCondition {
    public function __construct($free_condition) {
        $this->where_tokens[] = $free_condition;
    }
}