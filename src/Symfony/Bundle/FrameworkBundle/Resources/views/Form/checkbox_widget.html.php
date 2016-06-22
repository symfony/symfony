<input type="checkbox"
    <?php echo $view['form']->block($form, 'widget_attributes') ?>
    <?php if (strlen($value) > 0): ?> value="<?php echo $view->escape($value) ?>"<?php endif ?>
    <?php if ($checked): ?> checked="checked"<?php endif ?>
/>
