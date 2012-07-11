<table <?php echo $view['form']->renderBlock('widget_container_attributes') ?>>
    <?php if (!$form->hasParent()): ?>
    <?php echo $view['form']->errors($form) ?>
    <?php endif ?>
    <?php echo $view['form']->renderBlock('form_rows') ?>
    <?php echo $view['form']->rest($form) ?>
</table>
