<?php

class SectionBuffer {
    private $final_chunks = array();
    private $chunks = array();
    private $at = -1;
    private $reversed = false;

    public function __construct($reversed) {
        $this->reversed = $reversed;
    }

    public function enter() {
        $this->at++;
        if ($this->at >= 0)
            $chunks[$this->at] = "";
        ob_start();
    }

    public function leave() {
        static $calls = 0;
        $calls++;
        if ($calls > 100)
            throw new Exception("wat");
        $contents = ob_get_contents();
        if ($this->at > 0)
            $this->chunks[] = $contents;
        else {
            $this->final_chunks[] = $contents;
            $this->final_chunks = array_merge($this->final_chunks, $this->chunks);
            $this->chunks = array();
        }
        $this->at--;
        ob_end_clean();
    }
    
    public function output() {
        $output = "";
        $total = count($this->final_chunks);
        if ($this->reversed)
            for ($i = $total - 1; $i >= 0; $i--)
                $output .= $this->final_chunks[$i];
        else
            for ($i = 0; $i < $total; $i++)
                $output .= $this->final_chunks[$i];
        return $output;
    }
}

?>