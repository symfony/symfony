<ul>
    <?php foreach ($traces as $i => $trace): ?>
        <li>
            <?php echo $view->render('FrameworkBundle:Exception:trace', array('i' => $i, 'trace' => $trace)) ?>
        </li>
    <?php endforeach; ?>
</ul>
