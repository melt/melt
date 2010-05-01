<?php namespace nanomvc\nano_cms; ?>
<?php $this->layout->enterSection("head"); ?>
<style type="text/css">
#tree_admin {
    border-color: #efefef;
    border-style: solid;
    border-width: 1px;
    width: 196px;
}
#create_new_node {
    margin: 5px;
}
#node_admin {
    min-height: 362px;
    padding: 10px;
    width: 540px;
    border-color: #efefef;
    border-style: solid;
    border-width: 1px;
    float: right;
}
#url_preview {
    color: #9f9f9f;
    border-color: #efefef;
    border-style: solid;
    border-width: 1px;
    padding: 3px;
    overflow: hidden;
}
.jstree {
    padding: 10px 0px;
    overflow-x: scroll;
}
.jstree ins {
    border-style: none;
}
</style>
<?php $this->layout->exitSection(); ?>
<?php $this->view("/ctrl/elements/layout", array()); ?>
<h1>Edit Pages <img src="<?php echo url("/static/mod/iconize/crystal/24x24/filesystems/file_doc.png"); ?>" alt="" /></h1>
<div id="node_admin">
    <?php if (isset($this->site_node)): ?>
        <h2>
            <?php echo ($this->site_node->isLinked()? __("Editing"): __("Creating")) ?>
            <?php echo $this->site_node->getPageTypeName(); ?>
            <?php if ($this->site_node->isLinked()): ?>
                '<?php echo $this->site_node->view("title"); ?>'
            <?php elseif ($this->selected_site_node !== null): ?>
                in '<?php echo $this->selected_site_node->title; ?>'
            <?php else: ?>
                in root
            <?php endif; ?>
        </h2>
        <div id="url_preview"><?php echo escape($this->site_node->getUrl()); ?></div>
        <?php $this->view($this->site_node->getAdminViewPath()); ?>
    <?php endif; ?>
</div>
<div id="tree_admin">
    <div id="create_new_node">
        Create New:
        <select>
            <?php $rooted_in = isset($this->site_node)? "/" . $this->site_node->getID(): null; ?>
            <?php foreach (get_dynamic_pages() as $class_name => $name): ?>
            <option onclick="javascript: document.location = '<?php echo url("/nano_cms/admin/pages/new/" . \nanomvc\string\base64_alphanum_encode($class_name) . $rooted_in); ?>'">
                <?php echo escape($name); ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <script type="text/javascript">
        var can_redirect = false;
        jQuery.tree.defaults.callback.onhover = function(node, tree_obj) {
            can_redirect = true;
        };
        jQuery.tree.defaults.callback.beforemove = function(node, ref_node, type, tree_obj) {
            var ret = false;
            $.ajax({
                url: "<?php echo url("/nano_cms/admin/move_site_node"); ?>",
                data: {node: node.id, ref_node: ref_node.id, type: type},
                type: 'POST',
                success: function(data) { ret = data; },
                dataType: "json",
                async: false
            });
            var location = $("#" + node.id + " > a").attr('href');
            if (can_redirect)
                document.location = location;
            return ret;
        };
        jQuery.tree.defaults.callback.onselect = function(node, tree_obj) {
            var location = $("#" + node.id + " > a").attr('href');
            if (can_redirect)
                document.location = location;
        };
    </script>
    <?php $tree_id = \nanomvc\jquery\jstree_write($this->page_tree, "admin", $this->selected_site_node, "default", true); ?>
</div>