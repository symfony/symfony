<?php if (!empty($help)): ?>
    <?php $help_attr['class'] = isset($help_attr['class']) ? trim($help_attr['class'].' help-text') : 'help-text'; ?>
    <p id="<?php echo $view->escape($id); ?>_help" <?php echo ' '.$view['form']->block($form, 'attributes', ['attr' => $help_attr]); ?>><?php echo $view->escape(false !== $translation_domain ? $view['translator']->trans($help, [], $translation_domain) : $help); ?></p>
<?php endif; ?>
