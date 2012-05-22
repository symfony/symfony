<?php if ($errors): ?>
    <ul class="errors">
        <?php foreach ($errors as $error): ?>
            <li><?php
                if (null === $error->getMessagePluralization()) {
                    echo $view['translator']->trans(
                        $error->getMessageTemplate(),
                        $error->getMessageParameters(),
                        'validators'
                    );
                } else {
                    echo $view['translator']->transChoice(
                        $error->getMessageTemplate(),
                        $error->getMessagePluralization(),
                        $error->getMessageParameters(),
                        'validators'
                    );
                }?></li>
        <?php endforeach; ?>
    </ul>
<?php endif ?>
