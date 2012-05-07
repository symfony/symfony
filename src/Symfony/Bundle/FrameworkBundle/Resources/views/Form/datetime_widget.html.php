<?php if ($widget == 'single_text'): ?>
    <?php echo $view['form']->renderBlock('form_widget_single_control'); ?>
<?php else: ?>
    <div <?php echo $view['form']->renderBlock('widget_container_attributes') ?>>
        <?php echo $view['form']->widget($form['date']).' '.$view['form']->widget($form['time']) ?>
    </div>
<?php endif ?>
