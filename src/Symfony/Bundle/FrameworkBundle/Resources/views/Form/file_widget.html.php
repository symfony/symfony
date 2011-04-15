<div<?php echo $view['form']->attributes() ?>>
    <input type="file"
        id="<?php echo $view->escape($form['file']->getVar('id')) ?>"
        name="<?php echo $view->escape($form['file']->getVar('name')) ?>"
        <?php if ($form['file']->getVar('disabled')): ?>disabled="disabled"<?php endif ?>
        <?php if ($form['file']->getVar('required')): ?>required="required"<?php endif ?>
        <?php if ($form['file']->getVar('class')): ?>class="<?php echo $form['file']->getVar('class') ?>"<?php endif ?>
    />

    <?php echo $view['form']->widget($form['token']) ?>
    <?php echo $view['form']->widget($form['name']) ?>
</div>