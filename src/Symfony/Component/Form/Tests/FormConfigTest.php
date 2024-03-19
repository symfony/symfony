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
        return [
            ['isindex'],
            ['#'],
            ['a#'],
            ['a$'],
            ['a%'],
            ['a '],
            ["a\t"],
            ["a\n"],

            // Periods are allowed by the HTML4 spec, but disallowed by us
            // because they break the generated property paths
            ['a.'],
        ];
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
        return [
            ['z0'],
            ['A0'],
            ['A9'],
            ['Z0'],
            ['a-'],
            ['a_'],
            ['a:'],

            // Contrary to the HTML4 spec, we allow names starting with a
            // number, otherwise naming fields by collection indices is not
            // possible.
            // For root forms, leading digits will be stripped from the
            // "id" attribute to produce valid HTML4.
            ['0'],
            ['9'],

            // Contrary to the HTML4 spec, we allow names starting with an
            // underscore, since this is already a widely used practice in
            // Symfony.
            // For root forms, leading underscores will be stripped from the
            // "id" attribute to produce valid HTML4.
            ['_'],

            // Integers are allowed
            [0],
            [123],

            // NULL is allowed
            [null],

            // Allowed in HTML 5 specification
            // See: https://html.spec.whatwg.org/multipage/form-control-infrastructure.html#attr-fe-name
            ['_charset_'],
            ['-x'],
            [':x'],
            ['isINDEX'],

            // This value shouldn't be allowed.
            // However, many tests in Form component require empty name
            [''],
        ];
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
