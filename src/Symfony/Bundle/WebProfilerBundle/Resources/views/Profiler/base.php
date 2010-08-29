<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <title><?php $view->get('slots')->output('title', 'Profiler') ?></title>
        <?php echo $view->get('stylesheets') ?>
        <?php echo $view->get('javascripts') ?>
        <link href="<?php echo $view->get('assets')->getUrl('bundles/webprofiler/css/profiler.css') ?>" rel="stylesheet" type="text/css" media="screen" />
    </head>
    <body>
        <?php $view->get('slots')->output('_content') ?>
    </body>
</html>
