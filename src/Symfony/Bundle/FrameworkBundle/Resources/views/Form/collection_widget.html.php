<?php if (isset($prototype)): ?>
    <?php $attr['data-prototype'] = $view->escape($view['form']->row($prototype)) ?>
<?php endif ?>
<?php echo $view['form']->widget($form, ['attr' => $attr]) ?>
