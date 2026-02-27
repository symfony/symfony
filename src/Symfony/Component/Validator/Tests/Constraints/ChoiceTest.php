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
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;

class ChoiceTest extends TestCase
{
    public function testAttributes()
    {
        $metadata = new ClassMetadata(ChoiceDummy::class);
        $loader = new AttributeLoader();
        self::assertTrue($loader->loadClassMetadata($metadata));

        /** @var Choice $aConstraint */
        [$aConstraint] = $metadata->getPropertyMetadata('a')[0]->getConstraints();
        self::assertSame([1, 2], $aConstraint->choices);
        self::assertSame(['Default', 'ChoiceDummy'], $aConstraint->groups);

        /** @var Choice $bConstraint */
        [$bConstraint] = $metadata->getPropertyMetadata('b')[0]->getConstraints();
        self::assertSame(['foo', 'bar'], $bConstraint->choices);
        self::assertSame('myMessage', $bConstraint->message);
        self::assertSame(['Default', 'ChoiceDummy'], $bConstraint->groups);

        /** @var Choice $cConstraint */
        [$cConstraint] = $metadata->getPropertyMetadata('c')[0]->getConstraints();
        self::assertSame([1, 2], $aConstraint->choices);
        self::assertSame(['my_group'], $cConstraint->groups);
        self::assertSame('some attached data', $cConstraint->payload);

        /** @var Choice $stringIndexedConstraint */
        [$stringIndexedConstraint] = $metadata->getPropertyMetadata('stringIndexed')[0]->getConstraints();
        self::assertSame(['one' => 1, 'two' => 2], $stringIndexedConstraint->choices);
    }
}

class ChoiceDummy
{
    #[Choice(choices: [1, 2])]
    private $a;

    #[Choice(choices: ['foo', 'bar'], message: 'myMessage')]
    private $b;

    #[Choice(choices: [1, 2], groups: ['my_group'], payload: 'some attached data')]
    private $c;

    #[Choice(choices: ['one' => 1, 'two' => 2])]
    private $stringIndexed;
}
