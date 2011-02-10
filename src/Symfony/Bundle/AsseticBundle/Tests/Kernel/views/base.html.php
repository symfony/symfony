<!DOCTYPE html>
<html>
    <head>
        <title><?php $view['slots']->output('title') ?></title>
        <?php $view['slots']->output('stylesheets') ?>
    </head>
    <body>
        <?php $view['slots']->output('_content') ?>
        <?php $view['slots']->output('javascripts') ?>
    </body>
</html>
