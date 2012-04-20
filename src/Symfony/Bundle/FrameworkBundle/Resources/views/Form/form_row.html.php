<div>
    <?php echo $view['form']->label($form, isset($label) ? $label : null) ?>
    <?php if (!$form->hasChildren()): ?>
        <?php echo $view['form']->errors($form) ?>
    <?php endif ?>
    <?php echo $view['form']->widget($form) ?>
</div>
