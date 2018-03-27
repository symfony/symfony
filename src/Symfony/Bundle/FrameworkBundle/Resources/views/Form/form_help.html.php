<?php if (!empty($help)): ?>
    <p id="<?php echo $view->escape($id); ?>_help" class="help-text"><?php echo $view->escape(false !== $translation_domain ? $view['translator']->trans($help, array(), $translation_domain) : $help); ?></p>
<?php endif; ?>
