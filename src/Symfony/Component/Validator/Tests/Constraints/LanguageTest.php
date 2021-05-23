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
use Symfony\Component\Validator\Constraints\Language;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AnnotationLoader;

class LanguageTest extends TestCase
{
    public function testAttributes()
    {
        $metadata = new ClassMetadata(LanguageDummy::class);
        $loader = new AnnotationLoader();
        self::assertTrue($loader->loadClassMetadata($metadata));

        [$aConstraint] = $metadata->properties['a']->getConstraints();
        self::assertFalse($aConstraint->alpha3);

        [$bConstraint] = $metadata->properties['b']->getConstraints();
        self::assertSame('myMessage', $bConstraint->message);
        self::assertTrue($bConstraint->alpha3);
        self::assertSame(['Default', 'LanguageDummy'], $bConstraint->groups);

        [$cConstraint] = $metadata->properties['c']->getConstraints();
        self::assertSame(['my_group'], $cConstraint->groups);
        self::assertSame('some attached data', $cConstraint->payload);
    }
}

class LanguageDummy
{
    #[Language]
    private $a;

    #[Language(message: 'myMessage', alpha3: true)]
    private $b;

    #[Language(groups: ['my_group'], payload: 'some attached data')]
    private $c;
}
