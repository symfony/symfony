<?php use Symfony\Component\Form\ChoiceList\View\ChoiceGroupView;

$translatorHelper = $view['translator']; // outside of the loop for performance reasons! ?>
<?php $formHelper = $view['form']; ?>
<?php foreach ($choices as $group_label => $choice): ?>
    <?php if (is_array($choice) || $choice instanceof ChoiceGroupView): ?>
        <optgroup label="<?php echo $view->escape(false !== $choice_translation_domain ? $translatorHelper->trans($group_label, array(), $choice_translation_domain) : $group_label) ?>">
            <?php echo $formHelper->block($form, 'choice_widget_options', array('choices' => $choice)) ?>
        </optgroup>
    <?php else: ?>
        <option value="<?php echo $view->escape($choice->value) ?>" <?php echo $view['form']->block($form, 'attributes', array('attr' => $choice->attr)) ?><?php if ($is_selected($choice->value, $value)): ?> selected="selected"<?php endif?>><?php echo $view->escape(false !== $choice_translation_domain ? $translatorHelper->trans($choice->label, array(), $choice_translation_domain) : $choice->label) ?></option>
    <?php endif ?>
<?php endforeach ?>
