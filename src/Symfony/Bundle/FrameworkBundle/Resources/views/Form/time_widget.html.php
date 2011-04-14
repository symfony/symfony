<div<?php echo $view['form']->attributes() ?>>
    <?php
        // There should be no spaces between the colons and the widgets, that's why
        // this block is written in a single PHP tag
        echo $view['form']->widget($form['hour'], array('size' => 1));
        echo ':';
        echo $view['form']->widget($form['minute'], array('size' => 1));

        if ($with_seconds) {
            echo ':';
            echo $view['form']->widget($form['second'], array('size' => 1));
        }
    ?>
</div>