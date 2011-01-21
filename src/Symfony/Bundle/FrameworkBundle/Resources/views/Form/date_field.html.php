<?php if ($field->isField()): ?>
    <input type="text"
        id="<?php echo $field->getId() ?>"
        name="<?php echo $field->getName() ?>"
        value="<?php echo $field->getDisplayedData() ?>"
        <?php if ($field->isDisabled()): ?>disabled="disabled"<?php endif ?>
        <?php if ($field->isRequired()): ?>required="required"<?php endif ?>
        <?php echo $view['form']->attributes($attr) ?>
    />
<?php else: ?>
    <?php echo str_replace(array('{{ year }}', '{{ month }}', '{{ day }}'), array(
        $view['form']->render($field['year']),
        $view['form']->render($field['month']),
        $view['form']->render($field['day']),
    ), $field->getPattern()) ?>
<?php endif ?>
