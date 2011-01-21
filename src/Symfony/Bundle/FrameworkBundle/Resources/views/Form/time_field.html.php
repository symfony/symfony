<?php
    // There should be no spaces between the colons and the widgets, that's why
    // this block is written in a single PHP tag
    echo $view['form']->render($field['hour'], array('size' => 1));
    echo ':';
    echo $view['form']->render($field['minute'], array('size' => 1));

    if ($field->isWithSeconds()) {
        echo ':';
        echo $view['form']->render($field['second'], array('size' => 1));
    }
?>