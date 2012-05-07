<?php if ($single_control): ?>
<?php echo $view['form']->renderBlock('form_widget_single_control')?>
<?php else: ?>
<?php echo $view['form']->renderBlock('form_widget_compound')?>
<?php endif ?>
