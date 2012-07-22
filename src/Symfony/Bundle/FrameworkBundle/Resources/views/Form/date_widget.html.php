<?php if ($widget == 'single_text'): ?>
    <?php echo $view['form']->block($form, 'form_widget_simple'); ?>
<?php else: ?>
    <div <?php echo $view['form']->block($form, 'widget_container_attributes') ?>>
        <?php echo str_replace(array('{{ year }}', '{{ month }}', '{{ day }}'), array(
            $view['form']->widget($form['year']),
            $view['form']->widget($form['month']),
            $view['form']->widget($form['day']),
        ), $date_pattern) ?>
    </div>
<?php endif ?>
