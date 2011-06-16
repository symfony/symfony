id="<?php echo $view->escape($id) ?>"
name="<?php echo $view->escape($full_name) ?>"
<?php if ($read_only): ?>disabled="disabled" <?php endif ?>
<?php if ($required): ?>required="required" <?php endif ?>
<?php if ($max_length): ?>maxlength="<?php echo $view->escape($max_length) ?>" <?php endif ?>
<?php if ($pattern): ?>pattern="<?php echo $view->escape($pattern) ?>" <?php endif ?>
<?php foreach($attr as $k => $v) { printf('%s="%s" ', $view->escape($k), $view->escape($v)); } ?>

