id="<?php echo $view->escape($id) ?>"
name="<?php echo $view->escape($full_name) ?>"
<?php if ($disabled): ?>disabled="disabled" <?php endif ?>
<?php foreach ($attr as $k => $v): ?>
    <?php printf('%s="%s" ', $view->escape($k), $view->escape($v)) ?>
<?php endforeach; ?>
