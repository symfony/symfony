<?php if ($expanded): ?>
<?php echo $view['form']->block($form, 'choice_widget_expanded') ?>
<?php else: ?>
<?php echo $view['form']->block($form, 'choice_widget_collapsed') ?>
<?php endif ?>
