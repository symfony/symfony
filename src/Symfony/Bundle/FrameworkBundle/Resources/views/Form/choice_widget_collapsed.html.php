<select
    <?php echo $view['form']->block($form, 'widget_attributes', array(
        'required' => $required && (null !== $empty_value || $empty_value_in_choices)
    )) ?>
    <?php if ($multiple): ?> multiple="multiple"<?php endif ?>
>
    <?php if (null !== $empty_value): ?><option value=""<?php if ($required and empty($value) && "0" !== $value): ?> selected="selected"<?php endif?>><?php echo $view->escape($view['translator']->trans($empty_value, array(), $translation_domain)) ?></option><?php endif; ?>
    <?php if (count($preferred_choices) > 0): ?>
        <?php echo $view['form']->block($form, 'choice_widget_options', array('choices' => $preferred_choices)) ?>
        <?php if (count($choices) > 0 && null !== $separator): ?>
            <option disabled="disabled"><?php echo $separator ?></option>
        <?php endif ?>
    <?php endif ?>
    <?php echo $view['form']->block($form, 'choice_widget_options', array('choices' => $choices)) ?>
</select>
