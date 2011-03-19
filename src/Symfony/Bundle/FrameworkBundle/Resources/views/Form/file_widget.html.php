<input type="file"
    id="<?php echo $fields['file']->getVar('id') ?>"
    name="<?php echo $fields['file']->getVar('name') ?>"
    <?php if ($fields['file']->getVar('disabled')): ?>disabled="disabled"<?php endif ?>
    <?php if ($fields['file']->getVar('required')): ?>required="required"<?php endif ?>
    <?php if ($fields['file']->getVar('class')): ?>class="<?php echo $fields['file']->getVar('class') ?>"<?php endif ?>
/>

<?php echo $fields['token']->getWidget() ?>
<?php echo $fields['name']->getWidget() ?>