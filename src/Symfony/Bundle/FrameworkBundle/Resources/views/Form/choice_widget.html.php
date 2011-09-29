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
            <?php echo $view['form']->renderBlock('choice_options', array('options' => $preferred_choices)) ?>
            <?php if (count($choices) > 0 && null !== $separator): ?>
                <option disabled="disabled"><?php echo $separator ?></option>
            <?php endif ?>
        <?php endif ?>
        <?php echo $view['form']->renderBlock('choice_options', array('options' => $choices)) ?>
    </select>
<?php endif ?>
