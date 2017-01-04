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

if (class_exists('PHPUnit\Framework\AssertionFailedError')) {
    class AssertionFailedError extends \PHPUnit\Framework\AssertionFailedError
    {}
} else {
    class AssertionFailedError extends \PHPUnit_Framework_AssertionFailedError
    {}
}
