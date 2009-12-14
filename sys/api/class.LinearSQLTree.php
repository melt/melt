<?php

/**
* @desc An abstract tree of key value pair nodes and a built in depth-first iterator
*       so it can iterate trough the tree in a linear fashion. Constructed from SQL.
*/
class LinearSQLTree implements Iterator {
    /*
    * A linear representation of a tree represented as a pre order DFS search,
    * 0: The next value is a node on current level.
    * 1: Walking down from the last node.
    * -1: Walking up in the tree from last level.
    */
    private $data;
    private $at;

    /**
    * @desc Constructs this tree from a SQL result.
    *       It expects the column named $parent_ptr_name to point to the parent.
    *       This tree will be the root node in the SQL result (or one of them if there are several).
    * @param String $parent_ptr_name The name of the parent pointer column.
    */
    public function __construct($sql_result, $parent_ptr_name) {
        $this->data = array();
        $this->at = 0;
        // Empty tree?
        if ($sql_result->count() == 0)
            return;
        // Map the rows to their respective id's. (Transform)
        $id_result = array();
        while (false !== ($row = $sql_result->fetch()))
            $id_result[$row->id] = $row->as_array();
        // Map rows to their respective childs.
        $child_matrix = array();
        foreach ($id_result as $id => $row)
            $child_matrix[$row[$parent_ptr_name]][] = $id;
        // Find all roots.
        $roots = array();
        foreach ($id_result as $id => $row)
            if (!isset($id_result[$row[$parent_ptr_name]]))
                $roots[] = $id;
        // Search trough all roots and their children with DFS.
        $this->_linear_dfs_travel($roots, $id_result, $child_matrix);
    }
    private function _linear_dfs_travel($childs, $id_result, $child_matrix) {
        foreach ($childs as $child_id) {
            $this->data[] = 0;
            $this->data[] = $id_result[$child_id];
            if (isset($child_matrix[$child_id])) {
                $this->data[] = 1;
                $this->_linear_dfs_travel($child_matrix[$child_id], $id_result, $child_matrix);
                $this->data[] = -1;
            }
        }
    }

    function rewind() {
        $this->at = 0;
    }

    function current() {
        if (null === ($key = $this->key()))
            return null;
        if ($key == 0)
            return $this->data[$this->at + 1];
        else
            return null;
    }

    function key() {
        return $this->valid()? $this->data[$this->at]: null;
    }

    function next() {
        if (!$this->valid())
            return null;
        $this->at += ($this->key() == 0)? 2: 1;
        return $this->current();
    }

    function valid() {
        return isset($this->data[$this->at]);
    }
}
?>