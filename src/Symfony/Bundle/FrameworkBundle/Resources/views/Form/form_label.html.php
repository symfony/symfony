<?php if ($required) { $attr['class'] = (isset($attr['class']) ? $attr['class'] : '').' required'; } ?>
<?php if (!$form->hasChildren()) { $attr['for'] = $id; } ?>
<label <?php foreach($attr as $k => $v) { printf('%s="%s" ', $view->escape($k), $view->escape($v)); } ?>><?php echo $view->escape($view['translator']->trans($label, array(), $translation_domain)) ?></label>
