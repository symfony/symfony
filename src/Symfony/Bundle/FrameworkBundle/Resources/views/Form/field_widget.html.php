<input
    type="<?php echo isset($type) ? $view->escape($type) : "text" ?>"
    value="<?php echo $view->escape($value) ?>"
    <?php echo $view['form']->renderBlock('attributes') ?>
/>
