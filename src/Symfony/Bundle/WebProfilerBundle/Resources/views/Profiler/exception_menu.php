<?php if ($data->hasException()): ?>
    <span class="count"><?php echo $data->hasException() ?></span>
<?php endif; ?>
<img style="margin: 0 5px 0 0; vertical-align: middle; width: 32px" width="32" height="32" alt="Exception" src="<?php echo $view->get('assets')->getUrl('bundles/webprofiler/images/exception.png') ?>" />
Exception