<?php $view->extend('WebProfilerBundle:Profiler:base') ?>

<div class="header">
    <h1>
        <img alt="" src="<?php echo $view->get('assets')->getUrl('bundles/webprofiler/images/profiler.png') ?>" />
        Symfony Profiler
    </h1>
    <div>
        <em><?php echo $profiler->getUrl() ?></em> by <em><?php echo $profiler->getIp() ?></em> at <em><?php echo date('r', $profiler->getTime()) ?></em>
    </div>
</div>

<?php echo $view->get('actions')->render('WebProfilerBundle:Profiler:toolbar', array('token' => $token, 'position' => 'normal')) ?>

<table>
    <tr><td class="menu">
        <?php echo $view->get('actions')->render('WebProfilerBundle:Profiler:list', array('token' => $token, 'panel' => $panel)) ?>
    </td><td class="main">
        <div class="content">
            <?php $view->get('slots')->output('_content') ?>
        </div>
    </td></tr>
</table>
