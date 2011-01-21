<input type="file"
    id="<?php echo $field['file']->getId() ?>"
    name="<?php echo $field['file']->getName() ?>"
    <?php if ($field['file']->isDisabled()): ?>disabled="disabled"<?php endif ?>
    <?php if ($field['file']->isRequired()): ?>required="required"<?php endif ?>
    <?php echo $view['form']->attributes($attr) ?>
/>

<?php echo $view['form']->render($field['token']) ?>
<?php echo $view['form']->render($field['original_name']) ?>