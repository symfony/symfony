<?php foreach ($attr as $k => $v): ?>
<?php if ('placeholder' === $k || 'title' === $k): ?>
<?php printf('%s="%s" ', $view->escape($k), $view->escape(false !== $translation_domain ? $view['translator']->trans($v, [], $translation_domain) : $v)) ?>
<?php elseif (true === $v): ?>
<?php printf('%s="%s" ', $view->escape($k), $view->escape($k)) ?>
<?php elseif (false !== $v): ?>
<?php printf('%s="%s" ', $view->escape($k), $view->escape($v)) ?>
<?php endif ?>
<?php endforeach ?>
