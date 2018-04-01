<?php if (false !== $label): ?>
<?php if ($required) { $label_attr['class'] = trim((isset($label_attr['class']) ? $label_attr['class'] : '').' required'); } ?>
<?php if (!$compound) { $label_attr['for'] = $id; } ?>
<?php if (!$label) { $label = isset($label_format)
    ? strtr($label_format, array('%name%' => $name, '%id%' => $id))
    : $view['form']->humanize($name); } ?>
<label<?php if ($label_attr) { echo ' '.$view['form']->block($form, 'attributes', array('attr' => $label_attr)); } ?>><?php echo $view->escape(false !== $translation_domain ? $view['translator']->trans($label, array(), $translation_domain) : $label) ?></label>
<?php endif ?>
