<?php if (isset($empty_view)  && $empty_view): ?>
    <?php echo $view['form']->block($form, 'empty_row')?>
<?php else: ?>
    <?php if ($expanded): ?>
        <?php echo $view['form']->block($form, 'choice_widget_expanded') ?>
    <?php else: ?>
        <?php echo $view['form']->block($form, 'choice_widget_collapsed') ?>
    <?php endif ?>
<?php endif ?>
