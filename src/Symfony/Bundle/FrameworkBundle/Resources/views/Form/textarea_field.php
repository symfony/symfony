<textarea id="<?php echo $field->getId() ?>" name="<?php echo $field->getName() ?>" <?php if ($field->isDisabled()): ?>disabled="disabled"<?php endif ?>>
    <?php echo $view->escape($field->getDisplayedData()) ?>
</textarea>
