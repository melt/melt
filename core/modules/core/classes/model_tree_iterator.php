<?php namespace nanomvc\core;

/**
 * Allows flat foreach iteration trough the tree.
 * 1 indicates diving down a level
 * -1 indicates moving up a level.
 */
class ModelTreeIterator implements \Iterator {
    private $stack;
    /**
     * 0: Reached the end.
     * 1: About to return the current node. -> 1
     * 2: About to go to nodes branch that has more than zero items. Sends 1. -> 1
     * 3: About to return from branch. Sends -1. -> 0, 1, 3
     */
    private $state;
    private $count;

    public function __construct(ModelTree $root) {
        $this->stack = array($root->getBranch());
        $this->rewind();
    }

    public function rewind() {
        $this->stack = array($this->stack[0]);
        reset($this->stack[0]);
        $this->state = (count($this->stack[0]) > 0)? 2: 0;
        $this->count = 0;
    }

    public function current() {
        switch ($state) {
        case 0:
            return false;
        case 2:
            return 1;
        case 3:
            return -1;
        default:
            return current(end($stack))->getNode();
        }
    }

    public function key() {
        return $this->count;
    }

    public function next() {
        switch ($state) {
        case 0:
            return false;
        case 1:
            $current = current(end($stack));
            $branch = $current->getBranch();
            if (count($branch) > 0) {
                $this->state = 2;
            } else {
                $next = next(end($stack));
                if ($next === false)
                    $this->state = 3;
            }
            break;
        case 2:
            array_push($this->stack, current(end($stack))->getBranch());
            $this->state = 1;
            break;
        case 3:
            array_pop($this->stack);
            if (count($this->stack) == 0) {
                $this->state = 0;
            } else {
                $next = next(end($stack));
                if ($next !== false)
                    $this->state = 1;
            }
            break;
        }
        $this->count++;
        return current();
        
    }

    public function valid() {
        return $this->state !== 0;
    }
}