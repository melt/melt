<?php

/**
 * Starts building an inner selection query expression.
 * @param string $first_field The first field name in the selector.
 * @return nmvc\db\SelectQuery
 */
function expr($first_field) {
    $query = new \nmvc\db\SelectQuery();
    return $query->where($first_field);
}

/**
 * Signifies a field in a selection query.
 * @param string $field_name
 * @return \nmvc\db\ModelField
 */
function field($field_name) {
    return new \nmvc\db\ModelField($field_name);
}
