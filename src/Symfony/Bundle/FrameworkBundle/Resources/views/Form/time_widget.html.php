<?php if ($widget == 'single_text'): ?>
    <?php echo $view['form']->block($form, 'form_widget_simple'); ?>
<?php else: ?>
    <?php $vars = $widget == 'text' ? ['attr' => ['size' => 1]] : [] ?>
    <div <?php echo $view['form']->block($form, 'widget_container_attributes') ?>>
        <?php
            // There should be no spaces between the colons and the widgets, that's why
            // this block is written in a single PHP tag
            echo $view['form']->widget($form['hour'], $vars);

            if ($with_minutes) {
                echo ':';
                echo $view['form']->widget($form['minute'], $vars);
            }

            if ($with_seconds) {
                echo ':';
                echo $view['form']->widget($form['second'], $vars);
            }
        ?>
    </div>
<?php endif ?>
