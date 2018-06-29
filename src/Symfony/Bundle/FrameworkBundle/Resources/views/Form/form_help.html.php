<?php if (!empty($help)): ?>
    <?php $help_attr['class'] = isset($help_attr['class']) ? trim($help_attr['class'].' help-text') : 'help-text'; ?>
    <?php $help = false !== $translation_domain ? $view['translator']->trans($help, $help_translation_parameters, $translation_domain) : $help; ?>
    <?php $help = false === $help_html ? $view->escape($help) : $help ?>
    <p id="<?php echo $view->escape($id); ?>_help" <?php echo ' '.$view['form']->block($form, 'attributes', ['attr' => $help_attr]); ?>><?php echo $help; ?></p>
<?php endif; ?>
