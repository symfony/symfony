<input type="number"
    <?php echo $view['form']->attributes() ?>
    name="<?php echo $name ?>"
    value="<?php echo $value ?>"
    <?php if ($read_only): ?>disabled="disabled"<?php endif ?>
    <?php if ($required): ?>required="required"<?php endif ?>
    <?php if ($class): ?>class="<?php echo $class ?>"<?php endif ?>
/>