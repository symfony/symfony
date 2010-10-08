<?php echo $group->errors() ?>

<div>
    <?php foreach ($group as $field): ?>
        <?php echo $field->render() ?>
    <?php endforeach; ?>
</div>

<?php echo $group->hidden() ?>
