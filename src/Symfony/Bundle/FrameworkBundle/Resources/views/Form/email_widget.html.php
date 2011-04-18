<input type="email"
    <?php echo $view['form']->attributes() ?>
    name="<?php echo $view->escape($name) ?>"
    value="<?php echo $view->escape($value) ?>"
    <?php if ($max_length): ?>maxlength="<?php echo $view->escape($max_length) ?>"<?php endif ?>
    <?php if ($read_only): ?>disabled="disabled"<?php endif ?>
    <?php if ($required): ?>required="required"<?php endif ?>
/>