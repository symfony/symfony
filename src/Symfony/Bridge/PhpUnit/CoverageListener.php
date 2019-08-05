<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit;

if (version_compare(\PHPUnit\Runner\Version::id(), '6.0.0', '<')) {
    class_alias('Symfony\Bridge\PhpUnit\Legacy\CoverageListenerForV5', 'Symfony\Bridge\PhpUnit\CoverageListener');
} elseif (version_compare(\PHPUnit\Runner\Version::id(), '7.0.0', '<')) {
    class_alias('Symfony\Bridge\PhpUnit\Legacy\CoverageListenerForV6', 'Symfony\Bridge\PhpUnit\CoverageListener');
} else {
    class_alias('Symfony\Bridge\PhpUnit\Legacy\CoverageListenerForV7', 'Symfony\Bridge\PhpUnit\CoverageListener');
}

if (false) {
    class CoverageListener
    {
    }
}
