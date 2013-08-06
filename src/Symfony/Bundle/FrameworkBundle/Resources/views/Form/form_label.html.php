<?php if (false !== $label): ?>
<?php if ($required) { $label_attr['class'] = trim((isset($label_attr['class']) ? $label_attr['class'] : '').' required'); } ?>
<?php if (!$compound) { $label_attr['for'] = $id; } ?>
<?php if (!$label) { $label = $view['form']->humanize($name); } ?>
<label <?php foreach ($label_attr as $k => $v) { printf('%s="%s" ', $view->escape($k), $view->escape($v)); } ?>><?php echo $view->escape($view['translator']->trans($label, array(), $translation_domain)) ?></label>
<?php endif ?>
