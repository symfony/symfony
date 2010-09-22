[exception] <?php echo $exception->getStatusCode().' | '.$exception->getStatusText().' | '.$exception->getClass() ?>

[message] <?php echo $exception->getMessage() ?>

<?php if ($previousCount = count($exception->getPreviouses())): ?>
<?php echo $view->render('FrameworkBundle:Exception:traces.txt', array('exception' => $exception, 'position' => 0, 'count' => $previousCount)) ?>

<?php endif; ?>
<?php if ($previousCount): ?>
<?php foreach ($exception->getPreviouses() as $i => $previous): ?>
[linked exception] <?php echo $previous->getClass() ?>: <?php echo $previous->getMessage() ?>

<?php echo $view->render('FrameworkBundle:Exception:traces.txt', array('exception' => $previous, 'position' => $i + 1, 'count' => $previousCount)) ?>

<?php endforeach; ?>
<?php endif; ?>

[symfony] v. <?php echo \Symfony\Component\HttpKernel\Kernel::VERSION ?> (symfony-project.org)
[PHP]     v. <?php echo PHP_VERSION ?>
