<?php $type = isset($type) ? $type : 'text' ?>
<input type="<?php echo $type ?>" <?php $view['form']->renderBlock('attributes') ?> value="<?php echo $value ?>" rel="theme" />
