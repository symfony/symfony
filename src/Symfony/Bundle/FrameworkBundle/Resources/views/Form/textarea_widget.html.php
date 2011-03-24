<textarea
    id="<?php echo $id ?>"
    name="<?php echo $name ?>"
    <?php if ($disabled): ?>disabled="disabled"<?php endif ?>
    <?php if ($required): ?>required="required"<?php endif ?>
    <?php if ($class): ?>class="<?php echo $class ?>"<?php endif ?>
    <?php if (isset($attr)): echo $renderer->getTheme()->attributes($attr); endif; ?>
><?php
    echo $view->escape($value)
?></textarea>
