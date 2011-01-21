<?php foreach ($field->getAllHiddenFields() as $child): ?>
    <?php echo $view['form']->render($child) ?>
<?php endforeach; ?>
