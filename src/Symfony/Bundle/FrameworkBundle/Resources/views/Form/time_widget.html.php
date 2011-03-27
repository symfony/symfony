<?php
    // There should be no spaces between the colons and the widgets, that's why
    // this block is written in a single PHP tag
    echo $renderer['hour']->getWidget(array('size' => 1));
    echo ':';
    echo $renderer['minute']->getWidget(array('size' => 1));

    if ($with_seconds) {
        echo ':';
        echo $renderer['second']->getWidget(array('size' => 1));
    }
?>