<?php $view->get('stylesheets')->add('bundles/webprofiler/css/toolbar.css') ?>

<!-- START of Symfony 2 Web Debug Toolbar -->
<?php if ('normal' !== $position): ?>
    <div style="clear: both; height: 40px;"></div>
<?php endif; ?>
<div
    class="sf-toolbarreset"
    <?php if ('normal' !== $position): ?>
        style="position: <?php echo $position ?>;
        background: #cbcbcb;
        background-image: -moz-linear-gradient(-90deg, #e8e8e8, #cbcbcb);
        background-image: -webkit-gradient(linear, 0% 0%, 0% 100%, from(#e8e8e8), to(#cbcbcb));
        bottom: 0px;
        left:0;
        z-index: 6000000;
        width: 100%;
        border-top: 1px solid #bbb;
        padding: 5px;
        margin: 0;
        font: 11px Verdana, Arial, sans-serif;
        color: #000;
    "
    <?php endif; ?>
>
<?php foreach ($templates as $name => $template): ?>
    <?php echo $view->render($template, array('data' => $profiler->getCollector($name))) ?>
<?php endforeach; ?>
</div>
<!-- END of Symfony 2 Web Debug Toolbar -->
