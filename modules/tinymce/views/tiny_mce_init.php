<?php namespace nanomvc\tinymce; ?>
<script type="text/javascript">
tinyMCE.init({
	// General options
	mode : "exact",
	elements : "<?php echo $this->textarea_id; ?>",
        // Spell checking
        spellchecker_rpc_url : "<?php echo url("/tinymce/actions/spell_check"); ?>",
        // Relative URLs should be avoided according to nanoMVC best practice.
        relative_url : false,
        convert_urls : 0,
        remove_script_host : 0,
	<?php $this->view($this->config_class); ?>
});
</script>
