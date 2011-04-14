<div<?php echo $view['form']->attributes() ?>>
    <input type="file"
        id="<?php echo $context['file']->getVar('id') ?>"
        name="<?php echo $context['file']->getVar('name') ?>"
        <?php if ($context['file']->getVar('disabled')): ?>disabled="disabled"<?php endif ?>
        <?php if ($context['file']->getVar('required')): ?>required="required"<?php endif ?>
        <?php if ($context['file']->getVar('class')): ?>class="<?php echo $context['file']->getVar('class') ?>"<?php endif ?>
    />

    <?php echo $view['form']->widget($context['token']) ?>
    <?php echo $view['form']->widget($context['name']) ?>
</div>