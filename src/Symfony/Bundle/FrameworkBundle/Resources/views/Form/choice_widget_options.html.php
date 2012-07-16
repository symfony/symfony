<?php foreach ($choices as $index => $choice): ?>
    <?php if (is_array($choice)): ?>
        <optgroup label="<?php echo $view->escape($view['translator']->trans($index, array(), $translation_domain)) ?>">
            <?php echo $view['form']->block('choice_widget_options', array('choices' => $choice)) ?>
        </optgroup>
    <?php else: ?>
        <option value="<?php echo $view->escape($choice->value) ?>"<?php if ($choice->isSelected($value)): ?> selected="selected"<?php endif?>><?php echo $view->escape($view['translator']->trans($choice->label, array(), $translation_domain)) ?></option>
    <?php endif ?>
<?php endforeach ?>
