<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintChoiceWithPreset;

class ChoiceTest extends TestCase
{
    public function testSetDefaultPropertyChoice()
    {
        $constraint = new ConstraintChoiceWithPreset('A');

        self::assertEquals(['A', 'B', 'C'], $constraint->choices);
    }
}
