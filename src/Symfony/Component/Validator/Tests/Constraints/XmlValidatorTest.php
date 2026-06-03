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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Symfony\Component\Validator\Constraints\Xml;
use Symfony\Component\Validator\Constraints\XmlValidator;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\InvalidArgumentException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Symfony\Component\Validator\Tests\Constraints\Fixtures\StringableValue;

#[RequiresPhpExtension('simplexml')]
#[RequiresPhpExtension('dom')]
class XmlValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): XmlValidator
    {
        return new XmlValidator();
    }

    #[DataProvider('getValidXmlFormatValues')]
    public function testValidXmlFormatValue($value)
    {
        $this->validate($value, new Xml());
        $this->assertNoViolation();
    }

    public static function getValidXmlFormatValues(): array
    {
        return [
            ['<?xml version="1.0" encoding="utf-8" ?><code></code>'],
            ['<code></code>'],
            ['<test/>'],
            [file_get_contents(__DIR__.'/Fixtures/example.xml')],
            [new StringableValue('<test>test</test>')],
        ];
    }

    #[DataProvider('getInvalidXmlFormatValues')]
    public function testInvalidXmlFormatValue($value)
    {
        $constraint = new Xml(formatMessage: 'myMessage');
        $this->validate($value, $constraint);
        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$value.'"')
            ->setCode(Xml::INVALID_XML_ERROR)
            ->assertRaised();
    }

    public static function getInvalidXmlFormatValues(): array
    {
        return [
            ['test'],
            ['<?xml version="1" ?><code></code>'],
            ['<?xml version="1.0" encoding="" ?>'],
            ['<test><test>'],
            ['<test><test/'],
            ['<test>'],
            ['</test>'],
            ['<test><test/>'],
        ];
    }

    public function testValidXmlSchema()
    {
        $xml = file_get_contents(__DIR__.'/Fixtures/example.xml');

        $constraint = new Xml(schemaPath: __DIR__.'/Fixtures/example.xsd');

        $this->validate($xml, $constraint);
        $this->assertNoViolation();
    }

    public function testInvalidXmlSchema()
    {
        // Missing the required <author> element
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <book>
              <title>The Title</title>
            </book>
            XML;

        $constraint = new Xml(schemaPath: __DIR__.'/Fixtures/example.xsd');

        $this->validate($xml, $constraint);

        // Since the exact error message depends on libxml, we'll just check that a violation was raised
        // with the correct error code
        $violations = $this->context->getViolations();
        $this->assertCount(1, $violations);
        $this->assertEquals(Xml::INVALID_SCHEMA_ERROR, $violations[0]->getCode());
    }

    public function testXmlSchemaWithFlags()
    {
        $xml = file_get_contents(__DIR__.'/Fixtures/example.xml');

        // Use LIBXML_NONET flag to disallow network access during validation
        $constraint = new Xml(schemaPath: __DIR__.'/Fixtures/example.xsd', schemaFlags: \LIBXML_NONET);

        $this->validate($xml, $constraint);
        $this->assertNoViolation();
    }

    public function testInvalidSchemaThrowsException()
    {
        $xml = file_get_contents(__DIR__.'/Fixtures/example.xml');

        $constraint = new Xml(schemaPath: __DIR__.'/Fixtures/invalid.xsd');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The XSD schema file "%s" is not valid.', realpath(__DIR__.'/Fixtures/invalid.xsd')));

        $this->validate($xml, $constraint);
    }

    public function testTooLargePayloadRaisesViolation()
    {
        $constraint = new Xml(tooLargeMessage: 'myTooLargeMessage', maxSize: 64);

        $value = '<root>'.str_repeat('a', 80).'</root>';

        $this->validate($value, $constraint);

        $this->buildViolation('myTooLargeMessage')
            ->setParameter('{{ size }}', (string) \strlen($value))
            ->setParameter('{{ limit }}', '64')
            ->setCode(Xml::TOO_LARGE_ERROR)
            ->assertRaised();
    }

    public function testPayloadAtLimitIsValidated()
    {
        $value = '<root/>';
        $constraint = new Xml(maxSize: \strlen($value));

        $this->validate($value, $constraint);
        $this->assertNoViolation();
    }

    #[DataProvider('nonPositiveMaxSizes')]
    public function testNonPositiveMaxSizeIsRejected(int $maxSize)
    {
        $this->expectException(ConstraintDefinitionException::class);

        new Xml(maxSize: $maxSize);
    }

    public static function nonPositiveMaxSizes(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
    }
}
