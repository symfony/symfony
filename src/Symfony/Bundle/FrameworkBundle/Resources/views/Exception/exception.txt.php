[exception]   <?php echo $manager->getStatusCode().' | '.$manager->getStatusText().' | '.$manager->getName() ?>

[message]     <?php echo $manager->getMessage() ?>

<?php if (count($manager->getTraces())): ?>
[stack trace]
<?php foreach ($manager->getTraces() as $i => $trace): ?>
<?php echo $view->render('FrameworkBundle:Exception:trace.txt', array('i' => $i, 'trace' => $trace)) ?>

<?php endforeach; ?>

<?php endif; ?>
[symfony]     v. <?php echo \Symfony\Framework\Kernel::VERSION ?> (symfony-project.org)
[PHP]         v. <?php echo PHP_VERSION ?>
