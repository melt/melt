<?php namespace nanomvc\ctrl; ?>
<ul id="menu">
<?php foreach (get_admin_menu() as $category): ?>
    <li>
        <img src="<?php echo url($category['icon']); ?>" alt="" />
        <?php echo $category['category']; ?>
        <?php foreach ($category['paths'] as $url => $name): ?>
            <a href="<?php echo url($url); ?>"><?php echo $name; ?></a>
        <?php endforeach; ?>
    </li>
<?php endforeach; ?>
</ul>