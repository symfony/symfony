<div<?php echo $view['form']->attributes() ?>>
    <?php echo $view['form']->widget($context['date'])
        . ' '
        . $view['form']->widget($context['time']) ?>
</div>
