<?php

/**
 * Runs a php script that will dump a large amount of output and then quit.
 */

for ($i = 0; $i < 100000; $i++) {
    echo "Lorem ipsum dolor sit amet\n";
}
