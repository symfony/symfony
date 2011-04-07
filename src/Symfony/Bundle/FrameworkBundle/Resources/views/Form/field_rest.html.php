<?php foreach ($context->getChildren() as $context): ?>
    <?php if (!$context->isRendered()): ?>
        <?php echo $view['form']->widget($context) ?>
    <?php endif; ?>
<?php endforeach; ?>