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

use PHPUnit\Runner\Version;

if (version_compare(Version::id(), '11.0.0', '<')) {
    trait ExpectUserDeprecationMessageTrait
    {
        use ExpectDeprecationTrait;

        final protected function expectUserDeprecationMessage(string $expectedUserDeprecationMessage): void
        {
            $this->expectDeprecation(str_replace('%', '%%', $expectedUserDeprecationMessage));
        }
    }
} else {
    trait ExpectUserDeprecationMessageTrait
    {
    }
}
