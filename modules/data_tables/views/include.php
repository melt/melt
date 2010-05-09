<?php namespace nmvc\data_tables; ?>
<?php $this->layout->enterSection("head"); ?>
<style type="text/css" title="currentStyle">
        @import "<?php echo url("/static/mod/data_tables/demo_page.css"); ?>";
        @import "<?php echo url("/static/mod/data_tables/demo_table.css"); ?>";
        @import "<?php echo url("/static/mod/data_tables/demo_table_jui.css"); ?>";
        @import "<?php echo url("/static/mod/data_tables/extras.css"); ?>";
</style>
<script type="text/javascript">
    function do_batch_action(batch_url, batch_data, selection_ids) {
        var ids = new Array();
        $(selection_ids).each(function(key, elem) {
            if (elem)
                ids.push(key);
        });
        $.post(batch_url, {
                "data": batch_data,
                "ids": ids.join(',')
            }
        );
    }
</script>
<?php $this->layout->exitSection(); ?>