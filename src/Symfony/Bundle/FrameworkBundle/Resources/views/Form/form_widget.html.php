<?php if ($compound): ?>
<?php echo $view['form']->renderBlock('form_widget_compound')?>
<?php else: ?>
<?php echo $view['form']->renderBlock('form_widget_simple')?>
<?php endif ?>
