<?php if ($field->hasErrors()): ?>
    <ul>
        <?php foreach ($field->getErrors() as $error): ?>
            <li><?php echo $view['translator']->trans($error[0], $error[1], 'validators') ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif ?>
