<?php $type = isset($type) ? $type : 'text'; ?>
<input type="<?php echo $type; ?>" <?php $view['form']->block($form, 'widget_attributes'); ?> value="<?php echo $value; ?>" rel="theme" />
