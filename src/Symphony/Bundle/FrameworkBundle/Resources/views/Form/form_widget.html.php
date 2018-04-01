<?php if ($compound): ?>
<?php echo $view['form']->block($form, 'form_widget_compound')?>
<?php else: ?>
<?php echo $view['form']->block($form, 'form_widget_simple')?>
<?php endif ?>
