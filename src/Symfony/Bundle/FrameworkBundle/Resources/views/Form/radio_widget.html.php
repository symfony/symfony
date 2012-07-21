<input type="radio"
    <?php echo $view['form']->block($form, 'widget_attributes') ?>
    value="<?php echo $view->escape($value) ?>"
    <?php if ($checked): ?> checked="checked"<?php endif ?>
/>
