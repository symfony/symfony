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

// A trait to provide forward compatibility with newest PHPUnit versions

if (method_exists(\ReflectionMethod::class, 'hasReturnType') && (new \ReflectionMethod(TestCase::class, 'tearDown'))->hasReturnType()) {
    trait ForwardCompatTrait
    {
        use Legacy\ForwardCompatTraitForV8;
    }
} else {
    trait ForwardCompatTrait
    {
        use Legacy\ForwardCompatTraitForV5;
    }
}
