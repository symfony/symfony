<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Exception\InvalidArgumentException;
use Symfony\Component\Form\FormConfigBuilder;
use Symfony\Component\Form\NativeRequestHandler;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormConfigTest extends TestCase
{
    public static function provideInvalidFormInputName(): iterable
    {
        yield ['isindex'];

        yield ['#'];
        yield ['a#'];
        yield ['a$'];
        yield ['a%'];
        yield ['a '];
        yield ["a\t"];
        yield ["a\n"];
        // Periods are allowed by the HTML4 spec, but disallowed by us
        // because they break the generated property paths
        yield ['a.'];
    }

    /**
     * @dataProvider provideInvalidFormInputName
     */
    public function testInvalidFormInputName(string $name)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('The name "%s" contains illegal characters or equals to "isindex". Names should only contain letters, digits, underscores ("_"), hyphens ("-") and colons (":").', $name));

        new FormConfigBuilder($name, null, new EventDispatcher());
    }

    public static function provideValidFormInputName(): iterable
    {
        yield ['z0'];
        yield ['A0'];
        yield ['A9'];
        yield ['Z0'];
        yield ['a-'];
        yield ['a_'];
        yield ['a:'];
        // Contrary to the HTML4 spec, we allow names starting with a
        // number, otherwise naming fields by collection indices is not
        // possible.
        // For root forms, leading digits will be stripped from the
        // "id" attribute to produce valid HTML4.
        yield ['0'];
        yield ['9'];
        // Contrary to the HTML4 spec, we allow names starting with an
        // underscore, since this is already a widely used practice in
        // Symfony.
        // For root forms, leading underscores will be stripped from the
        // "id" attribute to produce valid HTML4.
        yield ['_'];
        // Integers are allowed
        yield [0];
        yield [123];
        // NULL is allowed
        yield [null];

        // Allowed in HTML 5 specification
        // See: https://html.spec.whatwg.org/multipage/form-control-infrastructure.html#attr-fe-name
        yield ['_charset_'];
        yield ['-x'];
        yield [':x'];
        yield ['isINDEX'];

        // This value shouldn't be allowed.
        // However, many tests in Form component require empty name
        yield [''];
    }

    /**
     * @dataProvider provideValidFormInputName
     */
    public function testValidFormInputName(string|int|null $name)
    {
        $formConfigBuilder = new FormConfigBuilder($name, null, new EventDispatcher());

        $this->assertSame((string) $name, $formConfigBuilder->getName());
    }

    public function testGetRequestHandlerCreatesNativeRequestHandlerIfNotSet()
    {
        $config = $this->getConfigBuilder()->getFormConfig();

        $this->assertInstanceOf(NativeRequestHandler::class, $config->getRequestHandler());
    }

    public function testGetRequestHandlerReusesNativeRequestHandlerInstance()
    {
        $config1 = $this->getConfigBuilder()->getFormConfig();
        $config2 = $this->getConfigBuilder()->getFormConfig();

        $this->assertSame($config1->getRequestHandler(), $config2->getRequestHandler());
    }

    public function testSetMethodAllowsGet()
    {
        $formConfigBuilder = $this->getConfigBuilder();
        $formConfigBuilder->setMethod('GET');

        self::assertSame('GET', $formConfigBuilder->getMethod());
    }

    public function testSetMethodAllowsPost()
    {
        $formConfigBuilder = $this->getConfigBuilder();
        $formConfigBuilder->setMethod('POST');

        self::assertSame('POST', $formConfigBuilder->getMethod());
    }

    public function testSetMethodAllowsPut()
    {
        $formConfigBuilder = $this->getConfigBuilder();
        $formConfigBuilder->setMethod('PUT');

        self::assertSame('PUT', $formConfigBuilder->getMethod());
    }

    public function testSetMethodAllowsDelete()
    {
        $formConfigBuilder = $this->getConfigBuilder();
        $formConfigBuilder->setMethod('DELETE');

        self::assertSame('DELETE', $formConfigBuilder->getMethod());
    }

    public function testSetMethodAllowsPatch()
    {
        $formConfigBuilder = $this->getConfigBuilder();
        $formConfigBuilder->setMethod('PATCH');

        self::assertSame('PATCH', $formConfigBuilder->getMethod());
    }

    private function getConfigBuilder($name = 'name')
    {
        return new FormConfigBuilder($name, null, new EventDispatcher());
    }
}
