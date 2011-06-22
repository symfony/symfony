<?php if ($expanded): ?>
    <div <?php echo $view['form']->renderBlock('container_attributes') ?>>
    <?php foreach ($form as $child): ?>
        <?php echo $view['form']->widget($child) ?>
        <?php echo $view['form']->label($child) ?>
    <?php endforeach ?>
    </div>
<?php else: ?>
    <select
        <?php echo $view['form']->renderBlock('attributes') ?>
        <?php if ($multiple): ?> multiple="multiple"<?php endif ?>
    >
        <?php if (null !== $empty_value): ?><option value=""><?php echo $view->escape($view['translator']->trans($empty_value)) ?></option><?php endif; ?>
        <?php if (count($preferred_choices) > 0): ?>
            <?php foreach ($preferred_choices as $choice => $label): ?>
                <?php if ($view['form']->isChoiceGroup($label)): ?>
                    <optgroup label="<?php echo $view->escape($choice) ?>">
                        <?php foreach ($label as $nestedChoice => $nestedLabel): ?>
                            <option value="<?php echo $view->escape($nestedChoice) ?>"<?php if ($view['form']->isChoiceSelected($form, $nestedChoice)): ?> selected="selected"<?php endif?>><?php echo $view->escape($nestedLabel) ?></option>
                        <?php endforeach ?>
                    </optgroup>
                <?php else: ?>
                    <option value="<?php echo $view->escape($choice) ?>"<?php if ($view['form']->isChoiceSelected($form, $choice)): ?> selected="selected"<?php endif?>><?php echo $view->escape($label) ?></option>
                <?php endif ?>
            <?php endforeach ?>
            <option disabled="disabled"><?php echo $separator ?></option>
        <?php endif ?>
        <?php foreach ($choices as $choice => $label): ?>
            <?php if ($view['form']->isChoiceGroup($label)): ?>
                <optgroup label="<?php echo $view->escape($choice) ?>">
                    <?php foreach ($label as $nestedChoice => $nestedLabel): ?>
                        <option value="<?php echo $view->escape($nestedChoice) ?>"<?php if ($view['form']->isChoiceSelected($form, $nestedChoice)): ?> selected="selected"<?php endif?>><?php echo $view->escape($nestedLabel) ?></option>
                    <?php endforeach ?>
                </optgroup>
            <?php else: ?>
                <option value="<?php echo $view->escape($choice) ?>"<?php if ($view['form']->isChoiceSelected($form, $choice)): ?> selected="selected"<?php endif?>><?php echo $view->escape($label) ?></option>
            <?php endif ?>
        <?php endforeach ?>
    </select>
<?php endif ?>
