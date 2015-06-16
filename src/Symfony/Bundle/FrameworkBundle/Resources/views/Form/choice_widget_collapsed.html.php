<select
    <?php if ($required && null === $empty_value && $empty_value_in_choices === false && $multiple === false):
        $required = false;
    endif; ?>
    <?php echo $view['form']->block($form, 'widget_attributes', array(
        'required' => $required,
    )) ?>
    <?php if ($multiple): ?> multiple="multiple"<?php endif ?>
>
    <?php if (null !== $empty_value): ?><option value=""<?php if ($required and empty($value) && '0' !== $value): ?> selected="selected"<?php endif?>><?php echo empty($empty_value) ? $empty_value : $view->escape($view['translator']->trans($empty_value, array(), $translation_domain)) ?></option><?php endif; ?>
    <?php if (count($preferred_choices) > 0): ?>
        <?php echo $view['form']->block($form, 'choice_widget_options', array('choices' => $preferred_choices)) ?>
        <?php if (count($choices) > 0 && null !== $separator): ?>
            <option disabled="disabled"><?php echo $separator ?></option>
        <?php endif ?>
    <?php endif ?>
    <?php echo $view['form']->block($form, 'choice_widget_options', array('choices' => $choices)) ?>
</select>
