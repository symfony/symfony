<?php
    // There should be no spaces between the colons and the widgets, that's why
    // this block is written in a single PHP tag
    echo $fields['hour']->getWidget(array('size' => 1));
    echo ':';
    echo $fields['minute']->getWidget(array('size' => 1));

    if ($with_seconds) {
        echo ':';
        echo $fields['second']->getWidget(array('size' => 1));
    }
?>