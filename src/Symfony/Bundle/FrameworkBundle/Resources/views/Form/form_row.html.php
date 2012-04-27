<div>
    <?php echo $view['form']->label($form, isset($label) ? $label : null) ?>
    <?php if ($single_control): ?>
        <?php echo $view['form']->errors($form) ?>
    <?php endif ?>
    <?php echo $view['form']->widget($form) ?>
</div>
