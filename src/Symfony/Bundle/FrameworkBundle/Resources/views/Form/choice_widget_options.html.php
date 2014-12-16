<?php $translatorHelper = $view['translator']; // outside of the loop for performance reasons! ?>
<?php $formHelper = $view['form']; ?>
<?php foreach ($choices as $index => $choice): ?>
    <?php if (is_array($choice)): ?>
        <optgroup label="<?php echo $view->escape($translatorHelper->trans($index, array(), $translation_domain)) ?>">
            <?php echo $formHelper->block($form, 'choice_widget_options', array('choices' => $choice)) ?>
        </optgroup>
    <?php else: ?>
        <?php
        $choiceLabel = $choice->label;
        if (isset($choice_label_format)) {
            $choiceLabel = strtr($choice_label_format, array('%name%' => $name, '%id%' => $id, '%value%' => $choice->value, '%choice%' => $choice->label));
        }
        ?>
        <option value="<?php echo $view->escape($choice->value) ?>"<?php if ($is_selected($choice->value, $value)): ?> selected="selected"<?php endif?>><?php echo $view->escape($translatorHelper->trans($choiceLabel, array(), $translation_domain)) ?></option>
    <?php endif ?>
<?php endforeach ?>
