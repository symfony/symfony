<?php if ($compound): ?>
    <?php if (isset($empty_view)  && $empty_view): ?>
        <?php echo $view['form']->block($form, 'empty_row')?>
        <?php echo $view['form']->rest($form) ?>
    <?php else: ?>
        <?php echo $view['form']->block($form, 'form_widget_compound')?>
    <?php endif ?>
<?php else: ?>
<?php echo $view['form']->block($form, 'form_widget_simple')?>
<?php endif ?>
