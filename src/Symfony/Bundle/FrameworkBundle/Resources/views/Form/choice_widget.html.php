<?php if ($expanded): ?>
    <?php foreach ($context as $choice => $child): ?>
        <?php echo $view['form']->widget($child) ?>
        <?php echo $view['form']->label($child) ?>
    <?php endforeach ?>
<?php else: ?>
    <select
        <?php echo $view['form']->attributes() ?>
        name="<?php echo $name ?>"
        <?php if ($read_only): ?> disabled="disabled"<?php endif ?>
        <?php if ($multiple): ?> multiple="multiple"<?php endif ?>
        <?php if ($class): ?> class="<?php echo $class ?>"<?php endif ?>
    >
        <?php if (!$required): ?><option value=""><?php echo $empty_value; ?></option><?php endif; ?>
        <?php if (count($preferred_choices) > 0): ?>
            <?php foreach ($preferred_choices as $choice => $label): ?>
                <?php if ($context->isChoiceGroup($label)): ?>
                    <optgroup label="<?php echo $choice ?>">
                        <?php foreach ($label as $nestedChoice => $nestedLabel): ?>
                            <option value="<?php echo $nestedChoice ?>"<?php if ($context->isChoiceSelected($nestedChoice)): ?> selected="selected"<?php endif?>><?php echo $nestedLabel ?></option>
                        <?php endforeach ?>
                    </optgroup>
                <?php else: ?>
                    <option value="<?php echo $choice ?>"<?php if ($context->isChoiceSelected($choice)): ?> selected="selected"<?php endif?>><?php echo $label ?></option>
                <?php endif ?>
            <?php endforeach ?>
            <option disabled="disabled"><?php echo $separator ?></option>
        <?php endif ?>
        <?php foreach ($choices as $choice => $label): ?>
            <?php if ($context->isChoiceGroup($label)): ?>
                <optgroup label="<?php echo $choice ?>">
                    <?php foreach ($label as $nestedChoice => $nestedLabel): ?>
                        <option value="<?php echo $nestedChoice ?>"<?php if ($context->isChoiceSelected($nestedChoice)): ?> selected="selected"<?php endif?>><?php echo $nestedLabel ?></option>
                    <?php endforeach ?>
                </optgroup>
            <?php else: ?>
                <option value="<?php echo $choice ?>"<?php if ($context->isChoiceSelected($choice)): ?> selected="selected"<?php endif?>><?php echo $label ?></option>
            <?php endif ?>
        <?php endforeach ?>
    </select>
<?php endif ?>
