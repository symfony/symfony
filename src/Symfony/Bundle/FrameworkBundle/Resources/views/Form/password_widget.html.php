<input type="password"
    id="<?php echo $id ?>"
    name="<?php echo $name ?>"
    value="<?php echo $value ?>"
    <?php if ($disabled): ?>disabled="disabled"<?php endif ?>
    <?php if ($required): ?>required="required"<?php endif ?>
    <?php if ($class): ?>class="<?php echo $class ?>"<?php endif ?>
    <?php if ($max_length && $max_length > 0): ?>maxlength="<?php echo $max_length; ?>"<?php endif; ?>
    <?php if ($size && $size > 0): ?>size="<?php echo $size; ?>"<?php endif; ?>
    <?php if (isset($attr)): echo $renderer->getTheme()->attributes($attr); endif; ?>
/>