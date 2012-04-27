<div>
    <?php echo $view['form']->label($form, isset($label) ? $label : null) ?>
    <?php echo $view['form']->errors($form) ?>
    <?php echo $view['form']->widget($form) ?>
</div>
