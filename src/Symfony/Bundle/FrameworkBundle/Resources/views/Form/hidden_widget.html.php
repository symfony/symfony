<input type="hidden"
    id="<?php echo $id ?>"
    name="<?php echo $name ?>"
    value="<?php echo $value ?>"
    <?php if ($read_only): ?>disabled="disabled"<?php endif ?>
    <?php if (isset($attr)): echo $view['form']->attributes($attr); endif; ?>
/>