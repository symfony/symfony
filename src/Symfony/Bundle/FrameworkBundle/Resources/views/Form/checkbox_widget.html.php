<input type="checkbox"
    <?php echo $view['form']->attributes() ?>
    name="<?php echo $view->escape($name) ?>"
    <?php if ($value): ?>value="<?php echo $view->escape($value) ?>"<?php endif ?>
    <?php if ($read_only): ?>disabled="disabled"<?php endif ?>
    <?php if ($required): ?>required="required"<?php endif ?>
    <?php if ($checked): ?>checked="checked"<?php endif ?>
/>
