<?php if (count($errors) > 0): ?>
    <ul>
        <?php foreach ($errors as $error): ?>
            <li><?php echo $view->escape($error->getMessage()) ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif ?>
