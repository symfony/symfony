<?php foreach ($options as $index => $choice): ?>
    <?php if ($view['form']->isChoiceGroup($choice)): ?>
        <optgroup label="<?php echo $view->escape($view['translator']->trans($index, array(), $translation_domain)) ?>">
            <?php foreach ($choice as $nested_index => $nested_choice): ?>
                <option value="<?php echo $view->escape($nested_choice) ?>"<?php if ($view['form']->isChoiceSelected($form, $nested_choice)): ?> selected="selected"<?php endif?>><?php echo $view->escape($view['translator']->trans($choice_labels[$nested_index], array(), $translation_domain)) ?></option>
            <?php endforeach ?>
        </optgroup>
    <?php else: ?>
        <option value="<?php echo $view->escape($choice) ?>"<?php if ($view['form']->isChoiceSelected($form, $choice)): ?> selected="selected"<?php endif?>><?php echo $view->escape($view['translator']->trans($choice_labels[$index], array(), $translation_domain)) ?></option>
    <?php endif ?>
<?php endforeach ?>
