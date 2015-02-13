<?php foreach ($form as $child): ?>
    <?php if (!$child->isRendered()): ?>
        <?php echo $view['form']->row($child) ?>
    <?php endif; ?>
<?php endforeach; ?>
