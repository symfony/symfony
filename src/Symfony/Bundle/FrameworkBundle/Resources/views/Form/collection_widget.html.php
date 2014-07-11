<?php $attr['data-form-widget'] = 'collection' ?>
<?php $attr['data-collection-allow-add'] = $allow_add ?>
<?php $attr['data-collection-allow-delete'] = $allow_delete ?>
<?php if (isset($prototype)): ?>
    <?php $attr['data-prototype-name'] = $prototype_name; ?>
    <?php $attr['data-prototype'] = $view->escape($view['form']->row($prototype)) ?>
<?php endif ?>
<?php echo $view['form']->widget($form, array('attr' => $attr)) ?>
