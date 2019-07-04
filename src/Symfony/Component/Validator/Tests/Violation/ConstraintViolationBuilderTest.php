<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Violation;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintA;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilder;

class ConstraintViolationBuilderTest extends TestCase
{
    public function testNonStringCode()
    {
        $constraintViolationList = new ConstraintViolationList();
        (new ConstraintViolationBuilder($constraintViolationList, new ConstraintA(), 'invalid message', [], null, 'foo', 'baz', new IdentityTranslator()))
            ->setCode(42)
            ->addViolation();

        self::assertSame('42', $constraintViolationList->get(0)->getCode());
    }
}
