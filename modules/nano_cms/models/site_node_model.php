<?php namespace nmvc\nano_cms;

abstract class SiteNodeModel extends \nmvc\url_mapper\UrlMappedModel implements \nmvc\jquery\HasJsTreeIcon {
    // Parent node.
    public $parent_id = array('core\SelectModelType', 'nano_cms\SiteNodeModel');

    // Callback used by url_mapper. What permalink alias the node wants.
    protected function getRequestedPermalinkAlias() {
        $requested_alias = $this->title;
        if ($this->parent_id !== null && $this->parent_id->url_map_id !== null)
            $requested_alias = $this->parent_id->url_map_id->url_alias . "/" . $requested_alias;
        return $requested_alias;
    }

    /** Friendly name of node type to display to user. */
    abstract static function getPageTypeName();

    /**
     * Should return a view path to the view that renders the administration
     * UI for this type of site node.
     */
    abstract static function getAdminViewPath();


    public function getURL($what_url = null) {
        if ($what_url == "admin")
            return url("/nano_cms/admin/pages/" . $this->getID());
        else
            return parent::getURL();
    }

    public function move($new_parent, $no_store = false) {
        // Move to new parent.
        $this->parent_id = $new_parent;
        // Generate new permalink.
        $this->createPermalink();
        // Notify all children that I was moved so they can get new permalinks.
        $children = $this->selectChildren('nmvc\nano_cms\SiteNodeModel');
        foreach ($children as $child)
            $child->move($this);
        if (!$no_store)
            $this->store();
    }

    public function beforeStore($is_linked) {
        // If renamed, node need to move (rename = move).
        if ($this->type("title")->hasChanged())
            $this->move($this->parent_id, true);
    }

    public function gcPointer($field_name) {
        if ($field_name == "parent_id") {
            // Move me up one level because parent is about to be deleted.
            $this->parent_id = $this->parent_id->parent_id;
            $this->store();
        } else
            parent::gcPointer($field_name);
    }
}

