<div<?php echo $view['form']->attributes() ?>>
    <?php echo $view['form']->errors($context); ?>
    <?php foreach ($context->getChildren() as $context): ?>
        <?php echo $view['form']->row($context); ?>
    <?php endforeach; ?>
    <?php echo $view['form']->rest($context) ?>
</div>

