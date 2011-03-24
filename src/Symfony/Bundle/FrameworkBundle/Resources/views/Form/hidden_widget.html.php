<input type="hidden"
    id="<?php echo $id ?>"
    name="<?php echo $name ?>"
    value="<?php echo $value ?>"
    <?php if ($disabled): ?>disabled="disabled"<?php endif ?>
    <?php if (isset($attr)): echo $renderer->getTheme()->attributes($attr); endif; ?>
/>