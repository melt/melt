<?php namespace melt\core;

class DocCommentParser {
    private $description = "";
    private $arguments_descs = array();
    private $argument_types = array();
    private $is_internal = false;
    private $is_deprecated = false;
    private $see = array();
    private $return_type = null;
    private $return_description = null;
    private $author = null;
    private $var_type = null;


    /**
     * Returns the variable type or NULL if no variable type.
     * @param string $default
     * @return string
     */
    public function getVariableType($default = null) {
        if ($this->var_type == null && $default != null)
            return $default;
        else
            return $this->var_type;
    }

    /**
     * Returns the doc comment description or NULL of no description.
     * @return string
     */
    public function getDescription() {
        return $this->description;
    }

    /**
     * Returns the argument description or NULL if there are no such argument.
     * @return string
     */
    public function getArgumentDescription($argument_name) {
        if ($argument_name[0] != '$')
            $argument_name = '$' . $argument_name;
        if (!isset($this->arguments_descs[$argument_name]))
            return null;
        else
            return $this->arguments_descs[$argument_name];
    }

    /**
     * Returns the argument type or NULL if there are no such argument.
     * @return string
     */
    public function getArgumentType($argument_name, $default = null) {
        if ($argument_name[0] != '$')
            $argument_name = '$' . $argument_name;
        if (!isset($this->argument_types[$argument_name]))
            $return = null;
        else
            $return = $this->argument_types[$argument_name];
        if ($default != null && $return == null)
            return $default;
        else
            return $return;
    }

    /**
     * Returns TRUE if doc comment specifies internal, otherwise FALSE.
     * @return boolean
     */
    public function isInternal() {
        return $this->is_internal == true;
    }

    /**
     * Returns TRUE if doc comment specifies depricated, otherwise FALSE.
     * @return boolean
     */
    public function isDeprecated() {
        return $this->is_deprecated;
    }

    /**
     * Returns an array of specified "See Also" items.
     * @return array
     */
    public function getSeeItems() {
        return $this->see;
    }

    /**
     * Returns the doc comment specified return type.
     * @return string
     */
    public function getReturnType($default = null) {
        if ($default != null && $this->return_type == null)
            return $default;
        return $this->return_type;
    }

    /**
     * Returns the doc comment specified return description.
     * @return string
     */
    public function getReturnDescription() {
        return $this->return_description;
    }

    /**
     * Returns the author.
     * @return string
     */
    public function getAuthor() {
        return $this->author;
    }

    /**
     * Parses the data specified by given doc comment.
     */
    public function __construct($doc_comment) {
        // Remove start and end junk.
        $doc_comment = preg_replace('#^[/\* \t]*#', "", $doc_comment);
        $doc_comment = preg_replace('#[/\* \t]*$#', "", $doc_comment);
        // Split in lines and eat line starting junk.
        $lines = \explode("\n", $doc_comment);
        foreach ($lines as $line) {
            $line = \preg_replace('#^[\s\*]*#', "", $line);
            if ($line[0] != '@') {
                $this->addToDescription($line);
            } else if (\melt\string\starts_with($line, '@desc ')) {
                $this->addToDescription(\substr($line, \strlen('@desc ')));
            } else if (\melt\string\starts_with($line, '@param ')) {
                if (\preg_match('#^@param\s+([^\s]+)\s*([^\s]+)\s*(.*)#', $line, $matches)) {
                    $type = $matches[1];
                    $param = $matches[2];
                    $desc = $matches[3];
                } else if (\preg_match('#^@param\s+([^\s]+)\s*(.*)#', $line, $matches)) {
                    $type = null;
                    $param = $matches[1];
                    $desc = $matches[2];
                } else {
                    // Broken @param
                    continue;
                }
                if ($param[0] != '$')
                    $param = '$' . $param;
                $this->argument_types[$param] = $type;
                $this->arguments_descs[$param] = $desc;
            } else if (\melt\string\starts_with($line, '@return ')) {
                // Ignore if @return is broken.
                if (!\preg_match('#^@return\s+([^\s]+)\s*(.*)#', $line, $matches))
                    continue;
                $this->return_type = $matches[1];
                $this->return_description =  $matches[2];
            } else if (\melt\string\starts_with($line, '@var ')) {
                if (!\preg_match('#^@var\s+([^\s]+)\s*(.*)#', $line, $matches))
                    continue;
                $this->var_type = $matches[1];
                if ($matches[2] != null)
                    $this->addToDescription($matches[2]);
            } else if (\melt\string\starts_with($line, '@see ')) {
                $this->see[] = \trim(\substr($line, strlen('@see ')));
            } else if (\melt\string\starts_with($line, '@internal')) {
                $this->is_internal = true;
            } else if (\melt\string\starts_with($line, '@deprec')) {
                $this->is_deprecated = true;
            } else if (\melt\string\starts_with($line, '@author')) {
                $this->author = \substr($line, strlen('@author '));
            }
        }
        if ($this->description == "")
            $this->description = null;
        else
            $this->description = \trim($this->description);
    }

    private function addToDescription($str) {
        $str = \trim($str);
        if ($this->description == null) {
            $this->description = $str;
        } else {
            $this->description .= "\n$str";
        }
    }

    public function fillGaps($doc_comment) {
        $doc_comment = new DocCommentParser($doc_comment);
        // For all documentation that does not exist, overlay with this.
        if ($this->description == null)
            $this->description = $doc_comment->description;
        if ($this->return_type == null)
            $this->return_type = $doc_comment->return_type;
        if ($this->return_description == null)
            $this->return_description = $doc_comment->return_description;
        if ($this->author == null)
            $this->author = $doc_comment->author;
        if (!$this->is_internal)
            $this->is_internal = $doc_comment->is_internal;
        if (!$this->is_deprecated)
            $this->is_deprecated = $doc_comment->is_deprecated;
        $this->arguments_descs = array_merge($doc_comment->arguments_descs, $this->arguments_descs);
        $this->argument_types = array_merge($doc_comment->argument_types, $this->argument_types);
        $this->see = array_merge($doc_comment->see, $this->see);
    }
}

