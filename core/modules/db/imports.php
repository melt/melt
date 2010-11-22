<?php

/**
 * Starts building an inner selection query expression.
 * @param string $first_field The first field name in the selector.
 * @return nmvc\db\WhereCondition
 */
function expr($first_field = null) {
    $wc = new \nmvc\db\WhereCondition();
    if ($first_field !== null)
        $wc->where($first_field);
    return $wc;
}

/**
 * Signifies a field in a selection query.
 * @param string $field_name
 * @return \nmvc\db\ModelField
 */
function field($field_name) {
    return new \nmvc\db\ModelField($field_name);
}
