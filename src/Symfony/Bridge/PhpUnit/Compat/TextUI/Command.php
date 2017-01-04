<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Compat\TextUI;

if (class_exists('PHPUnit\TextUI\Command')) {
    class Command extends \PHPUnit\TextUI\Command
    {}
} else {
    class Command extends \PHPUnit_TextUI_Command
    {}
}
