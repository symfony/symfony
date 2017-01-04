<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Compat\Framework;

if (class_exists(PHPUnit\Framework\TestSuite::class)) {
    class TestSuite extends \PHPUnit\Framework\TestSuite
    {}
} else {
    class TestSuite extends \PHPUnit_Framework_TestSuite
    {}
}
