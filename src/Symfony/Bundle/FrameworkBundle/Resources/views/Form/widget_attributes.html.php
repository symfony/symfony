id="<?php echo $view->escape($id) ?>" name="<?php echo $view->escape($full_name) ?>"<?php if ($disabled): ?> disabled="disabled"<?php endif ?>
<?php if ($required): ?> required="required"<?php endif ?>
<?php if (isset($helpBlockDisplayed) && true === $helpBlockDisplayed && !empty($help)): ?> aria-describedby="<?php echo $view->escape($id) ?>_help"<?php endif ?>
<?php echo $attr ? ' '.$view['form']->block($form, 'attributes') : '' ?>