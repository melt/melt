<?php namespace melt\db;

class IndexRequirement {
    private $constant_keys;
    private $order_keys;

    // Suberesolve piece is used to determine what model to verify index on
    // since a selection can target child models.
    private $subresolve;

    public function __construct(array $constant_keys, array $order_keys, $subresolve) {
        $this->constant_keys = $constant_keys;
        $this->order_keys = $order_keys;
        $this->subresolve = $subresolve;
    }

    public function getSubresolve() {
        return $this->subresolve;
    }

    /**
     * Verifies that this index requirement matches at least one of the
     * provided indexes.
     * @param array $indexes
     * @return void
     */
    public function verify($index_model) {
        $indexes = $index_model::getIndexes();
        $remaining_columns_base = array();
        foreach ($this->constant_keys as $key)
            $remaining_columns_base[$key] = 1;
        $total_fields = \count($this->constant_keys) + \count($this->order_keys);
        foreach ($indexes as $index) {
            if (\count($index["columns"]) < $total_fields)
                continue;
            $remaining_columns = $remaining_columns_base;
            foreach ($index["columns"] as $id => $column) {
                if (\count($remaining_columns) == 0)
                    break;
                // All constant keys must exists in the beginning of the index.
                $column = trim_id($column);
                if (!\array_key_exists($column, $remaining_columns))
                    goto next_index;
                unset($remaining_columns[$column]);
                unset($index["columns"][$id]);
            }
            if (\count($remaining_columns) > 0)
                continue;
            // The remaining columns are now the order keys
            // which are ordered so they must be in order.
            $remaining_columns = $this->order_keys;
            foreach ($index["columns"] as $column) {
                if (\count($remaining_columns) == 0)
                    break;
                $column = trim_id($column);
                if ($column !== \reset($remaining_columns))
                    goto next_index;
                unset($remaining_columns[\key($remaining_columns)]);
            }
            // Index verified, prefixed or full.
            return;
            next_index:
        }
        \trigger_error("The model '$index_model' does not have an index"
        . " appropriate for the ->byKey selection in given select query.", \E_USER_ERROR);
    }
}