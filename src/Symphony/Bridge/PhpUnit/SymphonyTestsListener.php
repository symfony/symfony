<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bridge\PhpUnit;

if (class_exists('PHPUnit_Runner_Version') && version_compare(\PHPUnit_Runner_Version::id(), '6.0.0', '<')) {
    class_alias('Symphony\Bridge\PhpUnit\Legacy\SymphonyTestsListenerForV5', 'Symphony\Bridge\PhpUnit\SymphonyTestsListener');
} elseif (version_compare(\PHPUnit\Runner\Version::id(), '7.0.0', '<')) {
    class_alias('Symphony\Bridge\PhpUnit\Legacy\SymphonyTestsListenerForV6', 'Symphony\Bridge\PhpUnit\SymphonyTestsListener');
} else {
    class_alias('Symphony\Bridge\PhpUnit\Legacy\SymphonyTestsListenerForV7', 'Symphony\Bridge\PhpUnit\SymphonyTestsListener');
}

if (false) {
    class SymphonyTestsListener
    {
    }
}
