<input type="url"
    id="<?php echo $field->getId() ?>"
    name="<?php echo $field->getName() ?>"
    value="<?php echo $field->getDisplayedData() ?>"
    <?php if ($field->isDisabled()): ?>disabled="disabled"<?php endif ?>
    <?php if ($field->isRequired()): ?>required="required"<?php endif ?>
    <?php echo $view['form']->attributes($attr) ?>
/>