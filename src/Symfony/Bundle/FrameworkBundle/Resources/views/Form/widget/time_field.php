<?php if ($origin->isField()): ?>
    <?php echo $generator->tag('input', $attributes) ?>
<?php else: ?>
    <?php echo $field['hour']->widget($attributes).':'.$field['minute']->widget($attributes) ?>

    <?php if ($origin->getOption('with_seconds')): ?>
        <?php echo ':'.$field['second']->widget($attributes) ?>
    <?php endif; ?>
<?php endif; ?>
