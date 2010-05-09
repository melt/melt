<?php namespace nmvc\url_mapper;

/**
 * URL mapped models have perma links to their display pages.
 * The default behaviour is to create a perma link as soon as they
 * are linked but this can be changed by overloading beforeStore().
 */
abstract class UrlMappedModel extends \nmvc\Model {
    public $url_map_id = array('core\SelectModelType', 'url_mapper\UrlMapModel');
    public $title = 'core\TextType';

    /** The invoke path this model should be mapped to. */
    abstract protected static function getInvokePath();

    /** Generates a permalink alias this instance requests.
     * Designed to be overridden. */
    protected function getRequestedPermalinkAlias() {
        return $this->title;
    }

    protected function createPermalink() {
        if ($this->url_map_id !== null)
            $this->url_map_id->unlink();
        $invoke_url = $this->getInvokePath() . "/" . $this->getID();
        $this->url_map_id = add_invoke_alias($this->getRequestedPermalinkAlias(), $invoke_url);
    }

    /**
     * Returns a URL that should display this model instance.
     * @return string Fully qualified URL to this model.
     */
    public function getURL() {
        if ($this->url_map_id == null)
            return null;
        return url("/" . $this->url_map_id->url_alias);
    }

    public function beforeStore($is_linked) {
        // Create permalink when linking for the first time.
        if (!$is_linked)
            $this->createPermalink();
    }

    public function afterUnlink() {
        // Garbage collect my URL mapping.
        if ($this->url_map_id !== null)
            $this->url_map_id->unlink();
    }

    public function __toString() {
        return escape($this->title);
    }
}
