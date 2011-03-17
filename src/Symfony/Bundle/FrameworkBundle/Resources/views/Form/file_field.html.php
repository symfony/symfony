<input type="file"
    id="<?php echo $field['file']->getVar('id') ?>"
    name="<?php echo $field['file']->getVar('name') ?>"
    <?php if ($field['file']->getVar('disabled')): ?>disabled="disabled"<?php endif ?>
    <?php if ($field['file']->getVar('required')): ?>required="required"<?php endif ?>
    <?php if ($field['file']->getVar('class')): ?>class="<?php echo $field['file']->getVar('class') ?>"<?php endif ?>
/>

<?php echo $fields['token']->getWidget() ?>
<?php echo $fields['original_name']->getWidget() ?>