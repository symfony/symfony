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

if (class_exists('PHPUnit\Framework\BaseTestListener')) {
    /**
     * Class BaseTestListener
     * @package Symfony\Bridge\PhpUnit\Compat\Framework
     * @internal
     */
    abstract class BaseTestListener extends \PHPUnit\Framework\BaseTestListener
    {}
} else {
    /**
     * Class BaseTestListener
     * @package Symfony\Bridge\PhpUnit\Compat\Framework
     * @internal
     */
    abstract class BaseTestListener extends \PHPUnit_Framework_BaseTestListener
    {}
}
