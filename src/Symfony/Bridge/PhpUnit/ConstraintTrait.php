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

use PHPUnit\Framework\Constraint\Constraint;

$r = new \ReflectionClass(Constraint::class);
if (!$r->getMethod('evaluate')->hasReturnType()) {
    trait ConstraintTrait
    {
        use Legacy\ConstraintTraitForV8;
    }
} else {
    trait ConstraintTrait
    {
        use Legacy\ConstraintTraitForV9;
    }
}
