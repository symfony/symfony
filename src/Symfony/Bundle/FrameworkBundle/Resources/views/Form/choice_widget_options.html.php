<?php foreach ($options as $index => $choice): ?>
    <?php if ($view['form']->isChoiceGroup($choice)): ?>
        <optgroup label="<?php echo $view->escape($view['translator']->trans($index, array(), $translation_domain)) ?>">
            <?php foreach ($choice as $nested_choice): ?>
                <option value="<?php echo $view->escape($nested_choice->value) ?>"<?php if ($view['form']->isChoiceSelected($form, $nested_choice)): ?> selected="selected"<?php endif?>><?php echo $view->escape($view['translator']->trans($nested_choice->label, array(), $translation_domain)) ?></option>
            <?php endforeach ?>
        </optgroup>
    <?php else: ?>
        <option value="<?php echo $view->escape($choice->value) ?>"<?php if ($view['form']->isChoiceSelected($form, $choice)): ?> selected="selected"<?php endif?>><?php echo $view->escape($view['translator']->trans($choice->label, array(), $translation_domain)) ?></option>
    <?php endif ?>
<?php endforeach ?>
