<?php if ($data->hasException()): ?>
    <div class="count"><?php echo $data->hasException() ?></div>
<?php endif; ?>
<img style="margin: 0 5px 0 0; vertical-align: middle; width: 32px" alt="" src="<?php echo $view->get('assets')->getUrl('bundles/webprofiler/images/exception.png') ?>" />
Exception