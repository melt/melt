<?php namespace nanomvc\url_mapper;

abstract class UrlMappedModel extends \nanomvc\Model {
    public $title = 'core\TextType';
    public $url_map = 'core\Select,url_mapper\UrlMap';

    /** The invoke path this model should be mapped to. */
    abstract protected static function getInvokePath();

    public function beforeStore($is_linked) {
        if ($this->url_map->ref !== null && $this->title->hasChanged()) {
            // Release the old URL mapping.
            $this->url_map->ref->unlink();
            // Make a new one.
            $invoke_url = "/url_mapper/callback/_model/" . get_class($this) . "/" . $this->getID();
            $this->url_map = add_invoke_alias($this->title, $invoke_url, true);
        }
    }

    public function afterUnlink() {
        // Garbage collect my URL mapping.
        if ($this->url_map->ref !== null)
            $this->url_map->ref->unlink();
    }
}
