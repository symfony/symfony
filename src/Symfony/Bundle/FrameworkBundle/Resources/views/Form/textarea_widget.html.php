<textarea
    <?php echo $view['form']->attributes() ?>
    name="<?php echo $view->escape($name) ?>"
    <?php if ($read_only): ?>disabled="disabled"<?php endif ?>
    <?php if ($required): ?>required="required"<?php endif ?>
><?php echo $view->escape($value) ?></textarea>
