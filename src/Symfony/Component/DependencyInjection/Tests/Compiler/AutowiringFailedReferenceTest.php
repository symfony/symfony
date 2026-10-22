<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\DependencyInjection\Compiler\AutowiringFailedReference;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\TypedReference;

class AutowiringFailedReferenceTest extends TestCase
{
    public function testSerializationPreservesReferenceWithoutEvaluatingAlternatives()
    {
        $reference = new TypedReference(\Countable::class, \Countable::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, 'counter', [new Target('items')]);
        $failedReference = new AutowiringFailedReference($reference, static function () {
            throw new \LogicException('Alternatives should not be evaluated during serialization.');
        });

        $restored = unserialize(serialize($failedReference));

        $this->assertSame((string) $reference, (string) $restored);
        $this->assertSame($reference->getType(), $restored->getType());
        $this->assertSame($reference->getInvalidBehavior(), $restored->getInvalidBehavior());
        $this->assertSame($reference->getName(), $restored->getName());
        $this->assertEquals($reference->getAttributes(), $restored->getAttributes());
        $this->assertSame('', $restored->getAlternatives());
    }

    public function testSerializationDoesNotDependOnAlternatives()
    {
        $reference = new TypedReference(\Countable::class, \Countable::class);
        $first = new AutowiringFailedReference($reference, static fn () => 'first');
        $second = new AutowiringFailedReference($reference, static fn () => 'second');

        $this->assertSame(serialize($first), serialize($second));
    }
}
