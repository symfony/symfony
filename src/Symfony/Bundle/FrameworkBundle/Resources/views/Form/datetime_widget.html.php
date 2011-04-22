<div<?php echo $view['form']->attributes() ?>>
    <?php echo $view['form']->widget($form['date'])
        . ' '
        . $view['form']->widget($form['time']) ?>
</div>
