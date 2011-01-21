<input type="text"
    id="<?php echo $field->getId() ?>"
    name="<?php echo $field->getName() ?>"
    value="<?php echo $field->getDisplayedData() ?>"
    <?php if ($field->isDisabled()): ?>disabled="disabled"<?php endif ?>
    <?php if ($field->isRequired()): ?>required="required"<?php endif ?>
    <?php if (!isset($attr['maxlength']) && $field->getMaxLength() > 0) $attr['maxlength'] = $field->getMaxLength() ?>
    <?php echo $view['form']->attributes($attr) ?>
/>