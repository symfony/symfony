<?php echo sprintf('<?xml version="1.0" encoding="%s" ?>', $view->getCharset())."\n" ?>
<error code="<?php echo $manager->getStatusCode() ?>" message="<?php echo $manager->getStatusText() ?>">
    <exception class="<?php echo $manager->getName() ?>" message="<?php echo $manager->getMessage() ?>">
        <?php echo $view->render('FrameworkBundle:Exception:traces', array('manager' => $manager, 'position' => 0, 'count' => count($managers))) ?>
    </exception>
<?php if (count($managers)): ?>
<?php foreach ($managers as $i => $previous): ?>
    <exception class="<?php echo $previous->getName() ?>" message="<?php echo $previous->getMessage() ?>">
        <?php echo $view->render('FrameworkBundle:Exception:traces', array('manager' => $previous, 'position' => $i + 1, 'count' => count($managers))) ?>
    </exception>
<?php endforeach; ?>
<?php endif; ?>
</error>
