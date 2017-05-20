<?php $method = strtoupper($method) ?>
<?php $form_method = $method === 'GET' || $method === 'POST' ? $method : 'POST' ?>
<form name="<?php echo $name ?>" method="<?php echo strtolower($form_method) ?>"<?php if ($action !== ''): ?> action="<?php echo $action ?>"<?php endif ?><?php foreach ($attr as $k => $v) { printf(' %s="%s"', $view->escape($k), $view->escape($v)); } ?><?php if ($multipart): ?> enctype="multipart/form-data"<?php endif ?>>
<?php if ($form_method !== $method): ?>
    <input type="hidden" name="_method" value="<?php echo $method ?>" />
<?php endif ?>
