<?php echo $view['form']->errors($field) ?>

<div>
    <?php foreach ($field->getVisibleFields() as $child): ?>
        <?php echo $view['form']->row($child) ?>
    <?php endforeach; ?>
</div>

<?php echo $view['form']->hidden($field) ?>
