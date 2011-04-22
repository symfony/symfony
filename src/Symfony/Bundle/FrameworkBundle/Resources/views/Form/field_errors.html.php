<?php if ($errors): ?>
    <ul>
        <?php foreach ($errors as $error): ?>
            <li><?php echo $view['translator']->trans(
                $error->getMessageTemplate(),
                $error->getMessageParameters(),
                'validators'
            ) ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif ?>
