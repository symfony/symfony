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

use Symfony\Bridge\PhpUnit\Legacy\ExpectDeprecationTraitBeforeV8_4;
use Symfony\Bridge\PhpUnit\Legacy\ExpectDeprecationTraitForV8_4;

if (version_compare(\PHPUnit\Runner\Version::id(), '8.4.0', '<')) {
    trait ExpectDeprecationTrait
    {
        use ExpectDeprecationTraitBeforeV8_4;
    }
} else {
    /**
     * @method void expectDeprecation(string $message)
     */
    trait ExpectDeprecationTrait
    {
        use ExpectDeprecationTraitForV8_4;
    }
}
