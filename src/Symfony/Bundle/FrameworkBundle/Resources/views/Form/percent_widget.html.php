<?php $symbol = false !== $symbol ? ($symbol ? ' '.$symbol : ' %') : '' ?>
<?php echo $view['form']->block($form, 'form_widget_simple', ['type' => $type ?? 'text']).$view->escape($symbol) ?>
