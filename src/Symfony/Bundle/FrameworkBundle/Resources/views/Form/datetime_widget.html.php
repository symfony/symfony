<div <?php echo $view['form']->renderBlock('container_attributes') ?>>
    <?php echo $view['form']->widget($form['date'])
        . ' '
        . $view['form']->widget($form['time']) ?>
</div>
