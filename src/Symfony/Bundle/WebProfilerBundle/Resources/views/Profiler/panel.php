<?php $view->extend('WebProfilerBundle:Profiler:layout.php') ?>

<?php echo $view->render($template, array('data' => $collector)) ?>
