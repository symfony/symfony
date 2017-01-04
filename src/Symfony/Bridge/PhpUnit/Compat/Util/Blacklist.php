<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Compat\Util;

if (class_exists('PHPUnit\Util\Blacklist')) {
    class Blacklist extends \PHPUnit\Util\Blacklist
    {}
} else {
    class Blacklist extends \PHPUnit_Util_Blacklist
    {}
}
