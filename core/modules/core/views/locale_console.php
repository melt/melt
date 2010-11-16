<?php namespace nmvc\core; ?>
<?php $this->layout->enterSection("head"); ?>
<title>NanoMVC Application Locale Console</title>
<style type="text/css">
    body {
        background-color: #efefef;
        font: 12px arial;
    }
    h1, p {
        margin-bottom: 16px;
    }
</style>
<?php $this->layout->exitSection(); ?>
<h1>NanoMVC Application Locale Console</h1>
<?php $locales = $this->engine->getLocales(); ?>
<p>
    There are currently <?php echo \count($locales); ?> installed locales
    in this application stored in /localization.php
</p>
<p>
    Locale set for current session is unknown as sessions is disabled for core.
    Use the LocalizationEngine to get/set locale.
</p>
<?php if (\count($locales) > 0): ?>
    <h2>Current Locales</h2>
    <ul>
        <?php foreach ($locales as $locale): ?>
            <li>
                Locale "<?php echo $locale; ?>"
                [<a href="<?php echo url("/core/action/locale/export/" . $locale) ?>">Export Translation</a>]
                [<a href="<?php echo url("/core/action/locale/remove/" . $locale) ?>">Remove</a>]
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
<h2>
    Create new locale:
</h2>
<form action="<?php echo url("/core/action/locale/create"); ?>" method="get">
    <p>
        <input type="text" name="locale" maxlength="2" />
        <input type="submit" value="Create" />
    </p>
</form>
<h2>
    Import changed translation from po file:
</h2>
<form action="<?php echo url("/core/action/locale/import"); ?>" enctype="multipart/form-data" method="post">
    <p>
        <input type="file" name="po_file" />
        <input type="submit" value="Upload" />
    </p>
</form>