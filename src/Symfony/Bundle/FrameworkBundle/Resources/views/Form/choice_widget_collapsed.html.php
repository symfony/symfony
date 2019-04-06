<select
    <?php if ($required && null === $placeholder && $placeholder_in_choices === false && $multiple === false && (!isset($attr['size']) || $attr['size'] <= 1)):
        $required = false;
    endif; ?>
    <?php echo $view['form']->block($form, 'widget_attributes', [
        'required' => $required,
    ]) ?>
    <?php if ($multiple): ?> multiple="multiple"<?php endif ?>
>
    <?php if (null !== $placeholder): ?><option value=""<?php if ($required && empty($value) && '0' !== $value): ?> selected="selected"<?php endif?>><?php echo '' != $placeholder ? $view->escape(false !== $translation_domain ? $view['translator']->trans($placeholder, [], $translation_domain) : $placeholder) : '' ?></option><?php endif; ?>
    <?php if (count($preferred_choices) > 0): ?>
        <?php echo $view['form']->block($form, 'choice_widget_options', ['choices' => $preferred_choices]) ?>
        <?php if (count($choices) > 0 && null !== $separator): ?>
            <option disabled="disabled"><?php echo $view->escape($separator) ?></option>
        <?php endif ?>
    <?php endif ?>
    <?php echo $view['form']->block($form, 'choice_widget_options', ['choices' => $choices]) ?>
</select>
