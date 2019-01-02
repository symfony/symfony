<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

// green builds: 6/7 with 97dab5f
// green builds: 3/4 without 97dab5f

/*
 * builds with the weird output assertion failure:
 * - https://ci.appveyor.com/project/fabpot/symfony/builds/21344209
 *
 * builds with a weirder output assertion failure:
 * - https://ci.appveyor.com/project/fabpot/symfony/builds/21344789#L1190
 *
 * builds where the process is not even running:
 * - https://ci.appveyor.com/project/fabpot/symfony/builds/21344543
 * - https://ci.appveyor.com/project/fabpot/symfony/builds/21345038
 * - https://ci.appveyor.com/project/fabpot/symfony/builds/21364454
 * - https://ci.appveyor.com/project/fabpot/symfony/builds/21364899
 */

$outputs = array(
    'First iteration output',
    'Second iteration output',
    'One more iteration output',
    'This took more time',
);

$iterationTime = 10000;

foreach ($outputs as $output) {
    usleep($iterationTime);
    $iterationTime *= 10;
    echo $output."\n";
}
