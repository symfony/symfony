<?php echo $group->errors() ?>

<table>
    <?php foreach ($group as $field): ?>
        <?php echo $field->render() ?>
    <?php endforeach; ?>
</table>

<?php echo $group->hidden() ?>
