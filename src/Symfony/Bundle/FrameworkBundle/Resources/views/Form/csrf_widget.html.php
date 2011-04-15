<?php if (!$form->hasParent() || !$form->getParent()->hasParent()): ?>
<input type="hidden"
    <?php echo $view['form']->attributes() ?>
    name="<?php echo $name ?>"
    value="<?php echo $value ?>"
    <?php if ($read_only): ?>disabled="disabled"<?php endif ?>
/>
<?php endif ?>