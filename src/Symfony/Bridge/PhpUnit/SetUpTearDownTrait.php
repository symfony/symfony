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

use PHPUnit\Framework\TestCase;

trigger_deprecation('symfony/phpunit-bridge', '5.3', 'The "%s" trait is deprecated, use original methods with "void" return typehint.', SetUpTearDownTrait::class);

// A trait to provide forward compatibility with newest PHPUnit versions
$r = new \ReflectionClass(TestCase::class);
if (!$r->getMethod('setUp')->hasReturnType()) {
    trait SetUpTearDownTrait
    {
        use Legacy\SetUpTearDownTraitForV7;
    }
} else {
    trait SetUpTearDownTrait
    {
        use Legacy\SetUpTearDownTraitForV8;
    }
}
