<?php if (!$label) {
    $label = $view['form']->humanize($name);
} ?>
<label>Custom label: <?php echo $view->escape($view['translator']->trans($label, array(), $translation_domain)) ?></label>
