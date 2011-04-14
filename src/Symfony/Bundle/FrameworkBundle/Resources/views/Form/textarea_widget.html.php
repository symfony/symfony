<textarea
    <?php echo $view['form']->attributes() ?>
    name="<?php echo $name ?>"
    <?php if ($read_only): ?>disabled="disabled"<?php endif ?>
    <?php if ($required): ?>required="required"<?php endif ?>
    <?php if ($class): ?>class="<?php echo $class ?>"<?php endif ?>
><?php
    echo $view->escape($value)
?></textarea>
