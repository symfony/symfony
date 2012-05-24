<div>
    <?php echo $view['form']->label($form, isset($label) ? $label : null) ?>
    <?php if (!$compound): ?>
        <?php echo $view['form']->errors($form) ?>
    <?php endif ?>
    <?php echo $view['form']->widget($form) ?>
</div>
