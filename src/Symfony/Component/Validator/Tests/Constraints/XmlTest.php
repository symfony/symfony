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

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\Xml;
use Symfony\Component\Validator\Exception\InvalidArgumentException;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;

#[RequiresPhpExtension('simplexml')]
#[RequiresPhpExtension('dom')]
class XmlTest extends TestCase
{
    public function testAttributes()
    {
        $metadata = new ClassMetadata(XmlDummy::class);
        $loader = new AttributeLoader();
        self::assertTrue($loader->loadClassMetadata($metadata));

        [$bConstraint] = $metadata->getPropertyMetadata('b')[0]->getConstraints();
        self::assertSame('myMessage', $bConstraint->formatMessage);
        self::assertSame('mySchemaMessage', $bConstraint->schemaMessage);
        self::assertSame(\LIBXML_NONET, $bConstraint->schemaFlags);
        self::assertSame(['Default', 'XmlDummy'], $bConstraint->groups);

        [$cConstraint] = $metadata->getPropertyMetadata('c')[0]->getConstraints();
        self::assertSame(['my_group'], $cConstraint->groups);
        self::assertSame('some attached data', $cConstraint->payload);
    }

    public function testInvalidSchemaPath()
    {
        $nonExistentFile = '/path/to/non-existent-file.xsd';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The XSD schema file "%s" does not exist or is unreadable.', $nonExistentFile));

        new Xml(schemaPath: $nonExistentFile);
    }
}

class XmlDummy
{
    #[Xml]
    private $a;

    #[Xml(formatMessage: 'myMessage', schemaMessage: 'mySchemaMessage', schemaFlags: \LIBXML_NONET)]
    private $b;

    #[Xml(groups: ['my_group'], payload: 'some attached data')]
    private $c;
}
