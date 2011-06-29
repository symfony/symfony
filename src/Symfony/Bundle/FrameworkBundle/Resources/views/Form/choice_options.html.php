<?php foreach ($options as $choice => $label): ?>
    <?php if ($view['form']->isChoiceGroup($label)): ?>
        <optgroup label="<?php echo $view->escape($view['translator']->trans($choice)) ?>">
            <?php foreach ($label as $nestedChoice => $nestedLabel): ?>
                <option value="<?php echo $view->escape($nestedChoice) ?>"<?php if ($view['form']->isChoiceSelected($form, $nestedChoice)): ?> selected="selected"<?php endif?>><?php echo $view->escape($view['translator']->trans($nestedLabel)) ?></option>
            <?php endforeach ?>
        </optgroup>
    <?php else: ?>
        <option value="<?php echo $view->escape($choice) ?>"<?php if ($view['form']->isChoiceSelected($form, $choice)): ?> selected="selected"<?php endif?>><?php echo $view->escape($view['translator']->trans($label)) ?></option>
    <?php endif ?>
<?php endforeach ?>
