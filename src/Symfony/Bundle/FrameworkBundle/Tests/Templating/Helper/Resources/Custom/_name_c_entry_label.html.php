<?php if (!$label) { $label = $view['form']->humanize($name); } ?>
<label>Custom name label: <?php echo $view->escape($view['translator']->transchoice($label, $translation_count, array(), $translation_domain)) ?></label>
