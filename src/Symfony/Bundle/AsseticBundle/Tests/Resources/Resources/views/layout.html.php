<?php $view->extend('::base.html.php') ?>

<?php $view['slots']->start('stylesheets') ?>
    <?php foreach($view['assetic']->stylesheets('stylesheet1.css, stylesheet2.css, @TestBundle/Resources/css/bundle.css') as $url): ?>
        <link href="<?php echo $view->escape($url) ?>" type="text/css" rel="stylesheet" />
    <?php endforeach; ?>
<?php $view['slots']->stop() ?>

<?php $view['slots']->start('javascripts') ?>
    <?php foreach($view['assetic']->javascripts('javascript1.js, javascript2.js') as $url): ?>
        <script src="<?php echo $view->escape($url) ?>"></script>
    <?php endforeach; ?>
<?php $view['slots']->stop() ?>

<?php foreach ($view['assetic']->image('logo.png') as $url): ?>
    <img src="<?php echo $view->escape($url) ?>">
<?php endforeach; ?>
