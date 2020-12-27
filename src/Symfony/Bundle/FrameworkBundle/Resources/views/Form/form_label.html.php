<?php if (false !== $label): ?>
<?php if ($required) { $label_attr['class'] = trim(($label_attr['class'] ?? '').' required'); } ?>
<?php if (!$compound) { $label_attr['for'] = $id; } ?>
<?php if (!$label) { $label = isset($label_format)
    ? strtr($label_format, ['%name%' => $name, '%id%' => $id])
    : $view['form']->humanize($name); } ?>
<label<?php if ($label_attr) { echo ' '.$view['form']->block($form, 'attributes', ['attr' => $label_attr]); } ?>><?php echo $view->escape(false !== $translation_domain ? $view['translator']->trans($label, $label_translation_parameters, $translation_domain) : $label) ?></label>
<?php endif ?>
