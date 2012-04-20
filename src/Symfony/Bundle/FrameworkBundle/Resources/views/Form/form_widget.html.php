<?php if ($primitive): ?>
<?php echo $view['form']->renderBlock('form_widget_primitive')?>
<?php else: ?>
<?php echo $view['form']->renderBlock('form_widget_complex')?>
<?php endif ?>
