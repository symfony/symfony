<input type="file"
    id="<?php echo $renderer['file']->getVar('id') ?>"
    name="<?php echo $renderer['file']->getVar('name') ?>"
    <?php if ($renderer['file']->getVar('disabled')): ?>disabled="disabled"<?php endif ?>
    <?php if ($renderer['file']->getVar('required')): ?>required="required"<?php endif ?>
    <?php if ($renderer['file']->getVar('class')): ?>class="<?php echo $renderer['file']->getVar('class') ?>"<?php endif ?>
/>

<?php echo $renderer['token']->getWidget() ?>
<?php echo $renderer['name']->getWidget() ?>