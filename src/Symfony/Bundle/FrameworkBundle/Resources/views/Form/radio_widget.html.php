<input type="radio"
    id="<?php echo $id ?>"
    name="<?php echo $name ?>"
    value="<?php echo $value ?>"
    <?php if ($read_only): ?>disabled="disabled"<?php endif ?>
    <?php if ($required): ?>required="required"<?php endif ?>
    <?php if ($checked): ?>checked="checked"<?php endif ?>
    <?php if ($class): ?>class="<?php echo $class ?>"<?php endif ?>
    <?php if (isset($attr)): echo $renderer->getTheme()->attributes($attr); endif; ?>
/>
