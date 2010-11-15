<?php echo $view['form']->errors($field) ?>

<div>
    <?php foreach ($field->getVisibleFields() as $child): ?>
        <div>
            <?php echo $view['form']->label($child) ?>
            <?php echo $view['form']->errors($child) ?>
            <?php echo $view['form']->render($child) ?>
        </div>
    <?php endforeach; ?>
</div>

<?php echo $view['form']->hidden($field) ?>
