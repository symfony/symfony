<?php if ($origin->isField()): ?>
    <?php echo $generator->tag('input', $attributes) ?>
<?php else: ?>
    <?php echo str_replace(array('{{ year }}', '{{ month }}', '{{ day }}'), array(
        $field['year']->widget($attributes),
        $field['month']->widget($attributes),
        $field['day']->widget($attributes),
    ), $origin->getPattern()) ?>
<?php endif; ?>
