<?php $view->get('stylesheets')->add('bundles/framework/css/exception.css') ?>

<h2>Exception</h2>

<?php if (!$data->hasException()): ?>
    <em>No exception was thrown and uncaught during the request.</em>
    <?php return; ?>
<?php endif; ?>

<?php echo $view->get('actions')->render('WebProfilerBundle:Exception:show', array('exception' => $data->getException(), 'format' => 'html')) ?>
