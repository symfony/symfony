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

use Symfony\Component\Validator\Constraints\Xml;
use Symfony\Component\Validator\Constraints\XmlValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Symfony\Component\Validator\Tests\Constraints\Fixtures\StringableValue;

final class XmlValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): XmlValidator
    {
        return new XmlValidator();
    }

    /**
     * @dataProvider getValidXmlValues
     */
    public function testValidXmlValue($value)
    {
        $this->validator->validate($value, new Xml());
        $this->assertNoViolation();
    }

    /**
     * @dataProvider getInvalidXmlValues
     */
    public function testInValidXmlValue($value)
    {
        $constraint = new Xml(message: 'myMessage');
        $this->validator->validate($value, $constraint);
        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$value.'"')
            ->setCode(Xml::INVALID_XML_ERROR)
            ->assertRaised();
    }

    public static function getValidXmlValues(): array
    {
        $xml = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <srv:container xmlns="http://symfony.com/schema/dic/security"
            xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
            xmlns:srv="http://symfony.com/schema/dic/services"
        >
            <config>
                <provider name="default">
                    <memory>
                        <user identifier="foo" password="foo" />
                    </memory>
                </provider>
                <firewall name="simple" pattern="/" security="false" />
            </config>
        </srv:container>
        XML;

        return [
            ['<?xml version="1.0" encoding="utf-8" ?><code></code>'],
            ['<code></code>'],
            ['<test/>'],
            [$xml],
            [new StringableValue('<test>test</test>')],
        ];
    }

    public static function getInvalidXmlValues(): array
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
}
