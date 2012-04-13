<?php if ($form->hasChildren()): ?>
<table <?php echo $view['form']->renderBlock('container_attributes') ?>>
    <?php echo $view['form']->renderBlock('form_rows') ?>
    <?php echo $view['form']->rest($form) ?>
</table>
<?php else: ?>
<?php echo $view['form']->renderBlock('input')?>
<?php endif ?>
