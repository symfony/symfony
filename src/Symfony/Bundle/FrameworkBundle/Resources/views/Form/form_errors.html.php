<?php if (count($errors)): ?>
    <ul>
        <?php foreach ($errors as $error): ?>
            <li><?php echo $error->getMessage() ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif ?>
