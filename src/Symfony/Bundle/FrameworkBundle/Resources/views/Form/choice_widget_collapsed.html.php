<select
    <?php if ($required && null === $placeholder && $placeholder_in_choices === false && $multiple === false):
        $required = false;
    endif; ?>
    <?php echo $view['form']->block($form, 'widget_attributes', array(
        'required' => $required,
    )) ?>
    <?php if ($multiple): ?> multiple="multiple"<?php endif ?>
>
    <?php if (null !== $placeholder): ?><option value=""<?php if ($required and empty($value) && "0" !== $value): ?> selected="selected"<?php endif?>><?php echo empty($placeholder) ? $placeholder : $view->escape($view['translator']->trans($placeholder, array(), $translation_domain)) ?></option><?php endif; ?>
    <?php if (count($preferred_choices) > 0): ?>
        <?php echo $view['form']->block($form, 'choice_widget_options', array('choices' => $preferred_choices)) ?>
        <?php if (count($choices) > 0 && null !== $separator): ?>
            <option disabled="disabled"><?php echo $separator ?></option>
        <?php endif ?>
    <?php endif ?>
    <?php echo $view['form']->block($form, 'choice_widget_options', array('choices' => $choices)) ?>
</select>
