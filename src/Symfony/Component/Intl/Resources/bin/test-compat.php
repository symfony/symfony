<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Intl\Intl;

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/autoload.php';

if (1 !== $GLOBALS['argc']) {
    bailout(<<<MESSAGE
Usage: php test-compat.php

Tests the compatibility of the current ICU version (bundled in ext/intl) with
different versions of symfony/icu.

For running this script, the intl extension must be loaded and all vendors
must have been installed through composer:

    composer install --dev

MESSAGE
    );
}

echo LINE;
echo centered("ICU Compatibility Test") . "\n";
echo LINE;

echo "Your ICU version: " . Intl::getIcuVersion() . "\n";

echo "Compatibility with symfony/icu:\n";

$branches = array(
    '1.1.x',
    '1.2.x',
);

cd(__DIR__ . '/../../vendor/symfony/icu/Symfony/Component/Icu');

foreach ($branches as $branch) {
    run('git checkout ' . $branch . ' 2>&1');

    exec('php ' . __DIR__ . '/util/test-compat-helper.php > /dev/null 2> /dev/null', $output, $status);

    echo "$branch: " . (0 === $status ? "YES" : "NO") . "\n";
}

echo "Done.\n";
