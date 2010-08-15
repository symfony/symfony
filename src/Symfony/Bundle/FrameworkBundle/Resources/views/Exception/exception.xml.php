<?php echo sprintf('<?xml version="1.0" encoding="%s" ?>', $view->getCharset())."\n" ?>
<error code="<?php echo $manager->getStatusCode() ?>" message="<?php echo $manager->getStatusText() ?>">
    <debug>
        <name><?php echo $manager->getName() ?></name>
        <message><?php echo htmlspecialchars($manager->getMessage(), ENT_QUOTES, $view->getCharset()) ?></message>
        <traces>
<?php foreach ($manager->getTraces() as $i => $trace): ?>
                <trace>
                <?php echo $view->render('FrameworkBundle:Exception:trace.txt', array('i' => $i, 'trace' => $trace)) ?>

                </trace>
<?php endforeach; ?>
        </traces>
    </debug>
</error>
