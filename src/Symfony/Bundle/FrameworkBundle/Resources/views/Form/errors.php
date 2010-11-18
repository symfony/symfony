<?php if ($field->hasErrors()): ?>
    <ul>
        <?php foreach ($field->getErrors() as $error): ?>
            <li><?php echo $view['translator']->trans(
                $error->getMessageTemplate(),
                $error->getMessageParameters()->getRawValue(),
                'validators'
            ) ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif ?>
