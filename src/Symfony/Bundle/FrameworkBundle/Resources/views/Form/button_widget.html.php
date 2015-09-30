<?php if (!$label) { $label = $view['form']->humanize($name); } ?>
<button type="<?php echo isset($type) ? $view->escape($type) : 'button' ?>" <?php echo $view['form']->block($form, 'button_attributes') ?>><?php echo $view->escape($translation_domain === false ? $label : $view['translator']->trans($label, array(), $translation_domain)) ?></button>
