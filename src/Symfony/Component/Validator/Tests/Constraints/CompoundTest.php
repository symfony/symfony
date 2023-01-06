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
use Symfony\Component\Validator\Constraints\Compound;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

class CompoundTest extends TestCase
{
    public function testItCannotRedefineConstraintsOption()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('You can\'t redefine the "constraints" option. Use the "Symfony\Component\Validator\Constraints\Compound::getConstraints()" method instead.');
        new EmptyCompound(['constraints' => [new NotBlank()]]);
    }

    public function testCanDependOnNormalizedOptions()
    {
        $constraint = new ForwardingOptionCompound($min = 3);

        $this->assertSame($min, $constraint->constraints[0]->min);
    }
}

class EmptyCompound extends Compound
{
    protected function getConstraints(array $options): array
    {
        return [];
    }
}

class ForwardingOptionCompound extends Compound
{
    public $min;

    public function getDefaultOption(): ?string
    {
        return 'min';
    }

    protected function getConstraints(array $options): array
    {
        return [
            new Length(['min' => $options['min'] ?? null]),
        ];
    }
}
