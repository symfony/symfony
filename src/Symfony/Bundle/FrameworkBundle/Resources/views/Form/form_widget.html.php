<?php if ($form->hasChildren()): ?>
<div <?php echo $view['form']->renderBlock('container_attributes') ?>>
    <?php echo $view['form']->renderBlock('form_rows') ?>
    <?php echo $view['form']->rest($form) ?>
</div>
<?php else: ?>
<?php echo $view['form']->renderBlock('input')?>
<?php endif ?>
