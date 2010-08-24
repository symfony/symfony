<?php if (count($manager->getTraces())): ?>
<?php foreach ($manager->getTraces() as $i => $trace): ?>
<?php echo $view->render('FrameworkBundle:Exception:trace.txt', array('i' => $i, 'trace' => $trace)) ?>

<?php endforeach; ?>
<?php endif;?>
