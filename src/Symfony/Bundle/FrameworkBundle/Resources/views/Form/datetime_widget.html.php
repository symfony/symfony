<?php if ($widget == 'single_text'): ?>
    <?php echo $view['form']->block('form_widget_simple'); ?>
<?php else: ?>
    <div <?php echo $view['form']->block('widget_container_attributes') ?>>
        <?php echo $view['form']->widget($form['date']).' '.$view['form']->widget($form['time']) ?>
    </div>
<?php endif ?>
