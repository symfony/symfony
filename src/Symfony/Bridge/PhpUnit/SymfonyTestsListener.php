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
    class_alias('Symfony\Bridge\PhpUnit\Legacy\SymfonyTestsListenerForV5', 'Symfony\Bridge\PhpUnit\SymfonyTestsListener');
} elseif (version_compare(\PHPUnit\Runner\Version::id(), '7.0.0', '<')) {
    class_alias('Symfony\Bridge\PhpUnit\Legacy\SymfonyTestsListenerForV6', 'Symfony\Bridge\PhpUnit\SymfonyTestsListener');
} else {
    class_alias('Symfony\Bridge\PhpUnit\Legacy\SymfonyTestsListenerForV7', 'Symfony\Bridge\PhpUnit\SymfonyTestsListener');
}

if (false) {
    class SymfonyTestsListener
    {
    }
}
