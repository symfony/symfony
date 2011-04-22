<?php if ($expanded): ?>
    <div<?php echo $view['form']->attributes() ?>>
    <?php foreach ($form as $choice => $child): ?>
        <?php echo $view['form']->widget($child) ?>
        <?php echo $view['form']->label($child) ?>
    <?php endforeach ?>
    </div>
<?php else: ?>
    <select
        <?php echo $view['form']->attributes() ?>
        name="<?php echo $view->escape($name) ?>"
        <?php if ($read_only): ?> disabled="disabled"<?php endif ?>
        <?php if ($multiple): ?> multiple="multiple"<?php endif ?>
    >
        <?php if (!$multiple && !$required): ?><option value=""><?php echo $empty_value; ?></option><?php endif; ?>
        <?php if (count($preferred_choices) > 0): ?>
            <?php foreach ($preferred_choices as $choice => $label): ?>
                <?php if ($form->isChoiceGroup($label)): ?>
                    <optgroup label="<?php echo $view->escape($choice) ?>">
                        <?php foreach ($label as $nestedChoice => $nestedLabel): ?>
                            <option value="<?php echo $view->escape($nestedChoice) ?>"<?php if ($form->isChoiceSelected($nestedChoice)): ?> selected="selected"<?php endif?>><?php echo $view->escape($nestedLabel) ?></option>
                        <?php endforeach ?>
                    </optgroup>
                <?php else: ?>
                    <option value="<?php echo $view->escape($choice) ?>"<?php if ($form->isChoiceSelected($choice)): ?> selected="selected"<?php endif?>><?php echo $view->escape($label) ?></option>
                <?php endif ?>
            <?php endforeach ?>
            <option disabled="disabled"><?php echo $separator ?></option>
        <?php endif ?>
        <?php foreach ($choices as $choice => $label): ?>
            <?php if ($form->isChoiceGroup($label)): ?>
                <optgroup label="<?php echo $view->escape($choice) ?>">
                    <?php foreach ($label as $nestedChoice => $nestedLabel): ?>
                        <option value="<?php echo $view->escape($nestedChoice) ?>"<?php if ($form->isChoiceSelected($nestedChoice)): ?> selected="selected"<?php endif?>><?php echo $view->escape($nestedLabel) ?></option>
                    <?php endforeach ?>
                </optgroup>
            <?php else: ?>
                <option value="<?php echo $view->escape($choice) ?>"<?php if ($form->isChoiceSelected($choice)): ?> selected="selected"<?php endif?>><?php echo $view->escape($label) ?></option>
            <?php endif ?>
        <?php endforeach ?>
    </select>
<?php endif ?>
