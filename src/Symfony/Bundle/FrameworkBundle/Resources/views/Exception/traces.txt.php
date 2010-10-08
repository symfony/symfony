<?php if (count($exception->getTrace())): ?>
<?php foreach ($exception->getTrace() as $i => $trace): ?>
<?php echo $view->render('FrameworkBundle:Exception:trace.txt.php', array('i' => $i, 'trace' => $trace)) ?>

<?php endforeach; ?>
<?php endif;?>
