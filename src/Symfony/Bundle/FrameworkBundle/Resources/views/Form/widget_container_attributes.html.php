<?php if (!empty($id)): ?>id="<?php echo $view->escape($id) ?>" <?php endif ?>
<?php foreach ($attr as $k => $v): ?>
<?php if (in_array($v, array('placeholder', 'title'), true)): ?>
<?php printf('%s="%s" ', $view->escape($k), $view->escape(false !== $translation_domain ? $view['translator']->trans($v, array(), $translation_domain) : $v)) ?>
<?php elseif ($v === true): ?>
<?php printf('%s="%s" ', $view->escape($k), $view->escape($k)) ?>
<?php elseif ($v !== false): ?>
<?php printf('%s="%s" ', $view->escape($k), $view->escape($v)) ?>
<?php endif ?>
<?php endforeach ?>
