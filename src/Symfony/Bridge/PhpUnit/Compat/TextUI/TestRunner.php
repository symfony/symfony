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

if (class_exists(PHPUnit\TextUI\TestRunner::class)) {
    class TestRunner extends \PHPUnit\TextUI\TestRunner
    {}
} else {
    class TestRunner extends \PHPUnit_TextUI_TestRunner
    {}
}
