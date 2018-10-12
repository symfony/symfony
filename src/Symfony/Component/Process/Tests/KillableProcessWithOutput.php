<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$outputs = array(
    'First iteration output',
    'Second iteration output',
    'One more iteration output',
    'This took more time',
    'This one was sooooo slow',
);

$iterationTime = 10000;

foreach ($outputs as $output) {
    usleep($iterationTime);
    $iterationTime *= 10;
    echo $output."\n";
}
