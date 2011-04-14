<?php foreach ($context->getChildren() as $context): ?>
    <?php if (!$context->isRendered()): ?>
        <?php echo $view['form']->row($context) ?>
    <?php endif; ?>
<?php endforeach; ?>