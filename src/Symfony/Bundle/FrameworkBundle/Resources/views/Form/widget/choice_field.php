<?php if ($origin->getOption('expanded')): ?>
    <?php foreach ($field as $child): ?>
        <?php echo $child->widget() ?>
    <?php endforeach; ?>
<?php else: ?>
    <?php echo $generator->contentTag('select',
        $generator->choices($origin->getPreferredChoices(), $origin->getOtherChoices(), $origin->getEmptyValue(), $origin->getSelected()),
        $attributes) ?>
<?php endif; ?>
