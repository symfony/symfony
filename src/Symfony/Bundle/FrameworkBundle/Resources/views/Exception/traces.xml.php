<traces>
<?php foreach ($manager->getTraces() as $i => $trace): ?>
        <trace>
        <?php echo $view->render('FrameworkBundle:Exception:trace.txt', array('i' => $i, 'trace' => $trace)) ?>

        </trace>
<?php endforeach; ?>
    </traces>
