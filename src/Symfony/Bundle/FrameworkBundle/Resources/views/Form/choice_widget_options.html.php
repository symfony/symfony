<?php $translatorHelper = $view['translator']; // outside of the loop for performance reasons! ?>
<?php $formHelper = $view['form']; ?>
<?php foreach ($choices as $index => $choice): ?>
    <?php if (is_array($choice)): ?>
        <optgroup label="<?php echo $view->escape($translatorHelper->trans($index, array(), $translation_domain)) ?>">
            <?php echo $formHelper->block($form, 'choice_widget_options', array('choices' => $choice)) ?>
        </optgroup>
    <?php else: ?>
        <option value="<?php echo $view->escape($choice->value) ?>"<?php if ($is_selected($choice->value, $value)): ?> selected="selected"<?php endif?>><?php echo $view->escape($translatorHelper->trans($choice->label, array(), $translation_domain)) ?></option>
    <?php endif ?>
<?php endforeach ?>
