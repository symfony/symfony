<div class="count"><?php echo sprintf('%0.0f', $data->getTime()) ?> ms</div>
<div class="count"><?php echo $data->getQueryCount() ?></div>
<img style="margin: 0 5px 0 0; vertical-align: middle; width: 32px" width="32" height="32" alt="Database" src="<?php echo $view->get('assets')->getUrl('bundles/webprofiler/images/db.png') ?>" />
Doctrine
