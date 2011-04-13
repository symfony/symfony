<?php
    // There should be no spaces between the colons and the widgets, that's why
    // this block is written in a single PHP tag
    echo $view['form']->widget($context['hour'], array('size' => 1));
    echo ':';
    echo $view['form']->widget($context['minute'], array('size' => 1));

    if ($with_seconds) {
        echo ':';
        echo $view['form']->widget($context['second'], array('size' => 1));
    }
?>