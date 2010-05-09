<?php namespace nmvc\core;

/** A tree constructed by one or more sets models with internal pointers. */
class ModelTree {
    private $node;
    private $branch = array();

    /**
     * Returns this node.
     * @return nmvc\Model
     */
    public function getNode() {
        return $this->node;
    }

    /**
     * Returns an array of ModelTree's on the sub branch.
     * @return array
     */
    public function getBranch() {
        return $this->branch;
    }

    /** This class cannot be constructed in any other context. */
    private function __construct() { }

    /**
     * Takes an array of model names mapped to parent pointer names
     * and outputs a Tree generated from it.
     * @param array $model_names Model names mapped to the parent pointer up in the tree.
     * @param array $wheres Model names mapped to where conditions.
     * @param array $orders Model names mapped to orderings.
     * @return ModelTree
     */
    public static function makeFromModel($model_names, $wheres = array(), $orderings = array()) {
        // Validate.
        foreach ($model_names as $model_name => $parent_pointer_name) {
            if (!class_exists($model_name) || !is_subclass_of($model_name, 'nmvc\Model'))
                trigger_error("'$model_name' is not a nmvc\\Model!", \E_USER_ERROR);
            $columns = $model_name::getColumnNames();
            if (substr($parent_pointer_name, -3) != "_id" || !isset($columns[$parent_pointer_name]))
                trigger_error("'$model_name' does not have a reference column named '$parent_pointer_name'.", \E_USER_ERROR);

        }
        $backlog = array();
        $out_array = array();
        // Select all rooted nodes and DFS from them.
        foreach ($model_names as $model_name => $parent_pointer_name) {
            $parent_model = $model_name::getTargetModel($parent_pointer_name);
            if (!isset($model_names[$parent_model])) {
                // All parents are rooted by definition. DFS from them.
                $where = isset($wheres[$parent_model])? $wheres[$parent_model]: null;
                $order = isset($orderings[$parent_model])? $orderings[$parent_model]: null;
                $parents = $parent_model::selectWhere($where, 0, 0, $order);
                foreach ($parents as $rooted_node)
                    self::dfs($backlog, $rooted_node, $out_array, $model_names, $parent_model, $wheres, $orderings);
            }
            $where = isset($wheres[$model_name])? $wheres[$model_name]: "1";
            $order = isset($orderings[$model_name])? $orderings[$model_name]: null;
            $rooted_nodes = $model_name::selectWhere("$where AND $parent_pointer_name <= 0", 0, 0, $order);
            foreach ($rooted_nodes as $rooted_node)
                self::dfs($backlog, $rooted_node, $out_array, $model_names, $model_name, $wheres, $orderings);
        }
        $out = new ModelTree();
        $out->node = null;
        $out->branch = $out_array;
        return $out;
    }

    /**
     * Depth first searches down this node.
     * @param array $backlog Backlog to keep track of what nodes we have added.
     * This even makes circular graphs into trees depending on where we started.
     * @param nmvc\Model $node The node to search from.
     * @param array $array_branch The branch in the array we're adding items on.
     * @param array $model_names Model names mapped to the parent pointer
     * up in the tree.
     * @param array $node_model_name Model name of the current pointer.
     * @param array $wheres Model names mapped to where conditions.
     * @param array $orders Model names mapped to orderings.
     */
    private static function dfs($backlog, \nmvc\Model $node, &$array_branch, $model_names, $node_model_name, $wheres = array(), $orderings = array()) {
        // Backlog check.
        if (isset($backlog[$node_model_name][$node->getID()]))
            return;
        $backlog[$node_model_name][$node->getID()] = true;
        // Every node is a potential subtree.
        $subtree = new ModelTree();
        $subtree->node = $node;
        $array_branch[] = $subtree;
        // Select all children of this node.
        foreach ($model_names as $model_name => $parent_pointer_name) {
            $parent_model = $model_name::getTargetModel($parent_pointer_name);
            if ($parent_model == $node_model_name) {
                $where = isset($wheres[$model_name])? $wheres[$model_name]: null;
                $order = isset($orderings[$model_name])? $orderings[$model_name]: null;
                $children = $node->selectChildren($model_name, $where, 0, 0, $order);
                foreach ($children as $child_node)
                    self::dfs($backlog, $child_node, $subtree->branch, $model_names, $model_name, $wheres, $orderings);
            }
        }
    }
}

