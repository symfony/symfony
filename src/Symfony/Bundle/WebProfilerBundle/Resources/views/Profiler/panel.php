<?php $view->extend('WebProfilerBundle:Profiler:layout') ?>

<?php echo $view->render($template, array('data' => $collector)) ?>
