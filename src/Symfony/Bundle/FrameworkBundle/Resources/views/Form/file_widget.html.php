<div<?php echo $view['form']->attributes() ?>>
    <input type="file"
        id="<?php echo $view->escape($form['file']->get('id')) ?>"
        name="<?php echo $view->escape($form['file']->get('name')) ?>"
        <?php if ($form['file']->get('disabled')): ?>disabled="disabled"<?php endif ?>
        <?php if ($form['file']->get('required')): ?>required="required"<?php endif ?>
    />

    <?php echo $view['form']->widget($form['token']) ?>
    <?php echo $view['form']->widget($form['name']) ?>
</div>
