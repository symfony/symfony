<input type="checkbox"
    <?php echo $view['form']->renderBlock('attributes') ?>
    <?php if ($value): ?> value="<?php echo $view->escape($value) ?>"<?php endif ?>
    <?php if ($checked): ?> checked="checked"<?php endif ?>
/>
