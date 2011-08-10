id="<?php echo $view->escape($id) ?>"
<?php foreach($attr as $k => $v) { printf('%s="%s" ', $view->escape($k), $view->escape($v)); } ?>
