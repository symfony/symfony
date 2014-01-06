<?php foreach ($choice->attr as $k => $v): ?>
data-<?php echo $k ?>="<?php echo $v ?>"
<?php endforeach ?>
<?php if ($choice->prototype): ?>
    data-prototype="<?php echo $view->escape($formHelper->block($choice->prototype, 'form_row')) ?>"
<?php endif ?>
