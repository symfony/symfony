<?php foreach ($fields as $field): ?>
    <?php if (!$field->isRendered()): ?>
        <?php echo $field->getWidget() ?>
    <?php endif; ?>
<?php endforeach; ?>