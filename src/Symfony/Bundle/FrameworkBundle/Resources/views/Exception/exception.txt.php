[exception] <?php echo $manager->getStatusCode().' | '.$manager->getStatusText().' | '.$manager->getName() ?>

[message] <?php echo $manager->getMessage() ?>

<?php if (count($manager->getTraces())): ?>
<?php echo $view->render('FrameworkBundle:Exception:traces', array('manager' => $manager, 'position' => 0, 'count' => count($managers))) ?>

<?php endif; ?>
<?php if (count($managers)): ?>
<?php foreach ($managers as $i => $previous): ?>
[linked exception] <?php echo $previous->getName() ?>: <?php echo $previous->getMessage() ?>

<?php echo $view->render('FrameworkBundle:Exception:traces', array('manager' => $previous, 'position' => $i + 1, 'count' => count($managers))) ?>

<?php endforeach; ?>
<?php endif; ?>

[symfony] v. <?php echo \Symfony\Framework\Kernel::VERSION ?> (symfony-project.org)
[PHP]     v. <?php echo PHP_VERSION ?>
