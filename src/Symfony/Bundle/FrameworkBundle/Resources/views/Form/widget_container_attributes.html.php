<?php if (!empty($id)): ?>id="<?php echo $view->escape($id) ?>"<?php endif ?>
<?php echo $attr ? ' '.$view['form']->block($form, 'attributes') : '' ?>
