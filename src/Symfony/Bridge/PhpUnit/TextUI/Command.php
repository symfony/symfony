<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\TextUI;

if (class_exists('PHPUnit_Runner_Version') && version_compare(\PHPUnit_Runner_Version::id(), '6.0.0', '<')) {
    class_alias('Symfony\Bridge\PhpUnit\Legacy\CommandForV5', 'Symfony\Bridge\PhpUnit\TextUI\Command');
} else {
    class_alias('Symfony\Bridge\PhpUnit\Legacy\CommandForV6', 'Symfony\Bridge\PhpUnit\TextUI\Command');
}

if (false) {
    class Command
    {
    }
}
