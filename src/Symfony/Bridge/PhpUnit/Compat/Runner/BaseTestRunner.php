<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Compat\Runner;

if (class_exists('PHPUnit\Runner\BaseTestRunner')) {
    /**
     * Class BaseTestRunner
     * @package Symfony\Bridge\PhpUnit\Compat\Runner
     * @internal
     */
    abstract class BaseTestRunner extends \PHPUnit\Runner\BaseTestRunner
    {}
} else {
    /**
     * Class BaseTestRunner
     * @package Symfony\Bridge\PhpUnit\Compat\Runner
     * @internal
     */
    abstract class BaseTestRunner extends \PHPUnit_Runner_BaseTestRunner
    {}
}
