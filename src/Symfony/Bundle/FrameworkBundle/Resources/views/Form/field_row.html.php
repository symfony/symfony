<div <?php foreach($attr as $k => $v) { printf('%s="%s" ', $view->escape($k), $view->escape($v)); } ?>>
    <?php echo $view['form']->label($form, null, $label) ?>
    <?php echo $view['form']->errors($form) ?>
    <?php echo $view['form']->widget($form, $widget) ?>
</div>
