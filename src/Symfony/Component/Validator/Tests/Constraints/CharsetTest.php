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
use Symfony\Component\Validator\Constraints\Charset;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;

class CharsetTest extends TestCase
{
    public function testSingleEncodingCanBeSet()
    {
        $encoding = new Charset('UTF-8');

        $this->assertSame('UTF-8', $encoding->encodings);
    }

    public function testMultipleEncodingCanBeSet()
    {
        $encoding = new Charset(['ASCII', 'UTF-8']);

        $this->assertSame(['ASCII', 'UTF-8'], $encoding->encodings);
    }

    public function testThrowsOnNoCharset()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('The "Symfony\Component\Validator\Constraints\Charset" constraint requires at least one encoding.');

        new Charset();
    }

    public function testAttributes()
    {
        $metadata = new ClassMetadata(CharsetDummy::class);
        $loader = new AttributeLoader();
        $this->assertTrue($loader->loadClassMetadata($metadata));

        [$aConstraint] = $metadata->properties['a']->getConstraints();
        $this->assertSame('UTF-8', $aConstraint->encodings);

        [$bConstraint] = $metadata->properties['b']->getConstraints();
        $this->assertSame(['ASCII', 'UTF-8'], $bConstraint->encodings);
    }
}

class CharsetDummy
{
    #[Charset('UTF-8')]
    private string $a;

    #[Charset(['ASCII', 'UTF-8'])]
    private string $b;
}
