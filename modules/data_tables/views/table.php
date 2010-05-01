<?php namespace nanomvc\data_tables; ?>
<?php include_dt(); ?>
<?php if (count($this->batch_ops) > 0): ?>
<div class="dt_batch_op">
    <strong>On <span id="<?php echo $this->uuid("ecount"); ?>">0</span> selected entries:</strong>
    <select disabled="disabled" id="<?php echo $this->uuid("select"); ?>">
        <option id="<?php echo $this->uuid("eo") ?>" selected="selected"></option>
        <?php foreach ($this->batch_ops as $action_url => $action_name): ?>
            <?php if (iconv_substr($action_name, 0, 1) == "!") { $action_name = iconv_substr($action_name, 1); $pre = "if (confirm('The action \'$action_name\' will be performed on the selected entries. Continue?'))"; } else { $pre = ""; } ?>
            <option onclick="javascript: $('#<?php echo $this->uuid("eo") ?>').attr('selected', true); <?php echo $pre; ?> { do_batch_action('<?php echo url($action_url); ?>', '<?php echo $this->batch_data; ?>', selection_<?php echo $this->id; ?>); <?php js_table_refresh($this->id); ?> };">
                <?php echo escape($action_name); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <input type="button" value="Unselect All" onclick="javascript: selection_<?php echo $this->id; ?> = new Array(); update_selected_count(); <?php js_table_refresh($this->id); ?>" />
</div>
<?php endif; ?>
<table cellpadding="0" cellspacing="0" border="0" class="data_tables display" id="<?php echo $this->id; ?>">
    <thead>
        <tr>
            <th>
                <input title="Select all entries on this page." type="checkbox" onclick="javascript: var checkall = this.checked; $('table#<?php echo $this->id; ?> tbody input[type=\'checkbox\']').each(function (key, elem) { if (checkall != elem.checked) elem.click(); });" />
            </th>
            <th>
                <?php if (strlen($this->insert_url) > 0): ?>
                    <a href="<?php echo url($this->insert_url); ?>" class="dt_insert_btn"></a>
                <?php endif; ?>
            </th>
            <?php foreach ($this->columns as $column_name => $column_label): ?>
                <th><?php echo escape($column_label); ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td colspan="5" class="dataTables_empty">Loading data from server</td>
        </tr>
    </tbody>
</table>
<?php $this->layout->enterSection("head"); ?>
<script type="text/javascript">
var current_ids = new Array();
var selection_<?php echo $this->id; ?> = new Array();
var data_table_<?php echo $this->id; ?>;
function update_selected_count() {
    var count = 0;
    $(selection_<?php echo $this->id; ?>).each(function(key, val) { if (val) count++; });
    $('#<?php echo $this->uuid("ecount"); ?>').html(count);
    $('#<?php echo $this->uuid("select"); ?>').attr('disabled', (count == 0));
}
$(document).ready(function() {
    data_table_<?php echo $this->id; ?> = $('#<?php echo $this->id; ?>').dataTable({
        <?php /* Disable sorting on first column. */ ?>
        "aoColumns": [
            {
                "bSortable": false,
                "fnRender": function(obj) {
                    var id = obj.aData[1];
                    var selected = selection_<?php echo $this->id; ?>[id];
                    var checked = selected? ' checked="checked" ': '';
                    return '\u003Cinput type="checkbox" ' + checked + ' onclick="javascript: selection_<?php echo $this->id; ?>[\'' + id + '\'] = this.checked; update_selected_count();" /\u003E';
                }
            },
            {
                "bSortable": false,
                "fnRender": function(obj) {
                    var id = obj.aData[1];
                    return '\u003Ca class="dt_view_btn" href="<?php echo $this->view_url; ?>' + id + '"\u003E\u003C/a\u003E';
                }
            }
            <?php foreach ($this->columns as $column_label): ?>,null<?php endforeach; ?>
        ],
        "aaSorting": [[2, 'asc']],
        "bJQueryUI": true,
        "bProcessing": true,
        "bServerSide": true,
        "bPaginate": true,
        "bAutoWidth": true,
        "bStateSave": false,
        "sPaginationType": "full_numbers",
        "sAjaxSource": "<?php echo $this->data_url; ?>"
    });
});
</script>
<?php $this->layout->exitSection(); ?>
