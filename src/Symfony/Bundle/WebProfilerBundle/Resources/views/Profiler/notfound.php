<?php $view->extend('WebProfilerBundle:Profiler:base') ?>

<div class="header">
    <h1>
        <img alt="" src="<?php echo $view->get('assets')->getUrl('bundles/webprofiler/images/profiler.png') ?>" />
        Symfony Profiler
    </h1>
    <div>
        Token "<?php echo $token ?>" does not exist.
    </div>
</div>

<div id="menu">
    <?php echo $view->get('actions')->render('WebProfilerBundle:Profiler:menu', array('token' => $token)) ?>
</div>
