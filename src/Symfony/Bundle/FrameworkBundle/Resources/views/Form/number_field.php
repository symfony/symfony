<input type="text"
	id="<?php echo $field->getId() ?>"
	name="<?php echo $field->getName() ?>"
	value="<?php echo $field->getDisplayedData() ?>"
	<?php if ($field->isDisabled()): ?>disabled="disabled"<?php endif ?>
/>