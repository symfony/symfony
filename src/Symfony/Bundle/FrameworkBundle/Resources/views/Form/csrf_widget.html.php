<?php if (!$form->hasParent() || !$form->getParent()->hasParent()): ?>
<input type="hidden"
    <?php echo $view['form']->attributes() ?>
    name="<?php echo $view->escape($name) ?>"
    value="<?php echo $view->escape($value) ?>"
    <?php if ($read_only): ?>disabled="disabled"<?php endif ?>
/>
<?php endif ?>