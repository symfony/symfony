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
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;

/**
 * @author Renan Taranto <renantaranto@gmail.com>
 */
class NotBlankTest extends TestCase
{
    public function testNormalizerCanBeSet()
    {
        $notBlank = new NotBlank(normalizer: 'trim');

        $this->assertEquals('trim', $notBlank->normalizer);
    }

    public function testAttributes()
    {
        $metadata = new ClassMetadata(NotBlankDummy::class);
        $loader = new AttributeLoader();
        self::assertTrue($loader->loadClassMetadata($metadata));

        [$aConstraint] = $metadata->getPropertyMetadata('a')[0]->getConstraints();
        self::assertFalse($aConstraint->allowNull);
        self::assertNull($aConstraint->normalizer);

        [$bConstraint] = $metadata->getPropertyMetadata('b')[0]->getConstraints();
        self::assertTrue($bConstraint->allowNull);
        self::assertSame('trim', $bConstraint->normalizer);
        self::assertSame('myMessage', $bConstraint->message);
    }
}

class NotBlankDummy
{
    #[NotBlank]
    private $a;

    #[NotBlank(normalizer: 'trim', allowNull: true, message: 'myMessage')]
    private $b;
}
