<?php if (!$compound): ?>
    <?php if ($errors): ?>
        <ul>
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
<?php else: ?>
    <?php if (count($errors) > 0): ?>
    <tr>
        <td colspan="2">
        <?php if ($errors): ?>
            <ul>
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
        </td>
    </tr>
    <?php endif; ?>
<?php endif; ?>
