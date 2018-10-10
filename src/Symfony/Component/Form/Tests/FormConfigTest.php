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
use Symfony\Component\Form\FormConfigBuilder;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormConfigTest extends TestCase
{
    public function getHtml4Ids()
    {
        return array(
            array('z0'),
            array('A0'),
            array('A9'),
            array('Z0'),
            array('#', 'Symfony\Component\Form\Exception\InvalidArgumentException'),
            array('a#', 'Symfony\Component\Form\Exception\InvalidArgumentException'),
            array('a$', 'Symfony\Component\Form\Exception\InvalidArgumentException'),
            array('a%', 'Symfony\Component\Form\Exception\InvalidArgumentException'),
            array('a ', 'Symfony\Component\Form\Exception\InvalidArgumentException'),
            array("a\t", 'Symfony\Component\Form\Exception\InvalidArgumentException'),
            array("a\n", 'Symfony\Component\Form\Exception\InvalidArgumentException'),
            array('a-'),
            array('a_'),
            array('a:'),
            // Periods are allowed by the HTML4 spec, but disallowed by us
            // because they break the generated property paths
            array('a.', 'Symfony\Component\Form\Exception\InvalidArgumentException'),
            // Contrary to the HTML4 spec, we allow names starting with a
            // number, otherwise naming fields by collection indices is not
            // possible.
            // For root forms, leading digits will be stripped from the
            // "id" attribute to produce valid HTML4.
            array('0'),
            array('9'),
            // Contrary to the HTML4 spec, we allow names starting with an
            // underscore, since this is already a widely used practice in
            // Symfony.
            // For root forms, leading underscores will be stripped from the
            // "id" attribute to produce valid HTML4.
            array('_'),
            // Integers are allowed
            array(0),
            array(123),
            // NULL is allowed
            array(null),
            // Other types are not
            array(1.23, 'Symfony\Component\Form\Exception\UnexpectedTypeException'),
            array(5., 'Symfony\Component\Form\Exception\UnexpectedTypeException'),
            array(true, 'Symfony\Component\Form\Exception\UnexpectedTypeException'),
            array(new \stdClass(), 'Symfony\Component\Form\Exception\UnexpectedTypeException'),
        );
    }

    /**
     * @dataProvider getHtml4Ids
     */
    public function testNameAcceptsOnlyNamesValidAsIdsInHtml4($name, $expectedException = null)
    {
        $dispatcher = $this->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcherInterface')->getMock();

        if (null !== $expectedException && method_exists($this, 'expectException')) {
            $this->expectException($expectedException);
        } elseif (null !== $expectedException) {
            $this->setExpectedException($expectedException);
        }

        $formConfigBuilder = new FormConfigBuilder($name, null, $dispatcher);

        $this->assertSame((string) $name, $formConfigBuilder->getName());
    }

    public function testGetRequestHandlerCreatesNativeRequestHandlerIfNotSet()
    {
        $config = $this->getConfigBuilder()->getFormConfig();

        $this->assertInstanceOf('Symfony\Component\Form\NativeRequestHandler', $config->getRequestHandler());
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
        $dispatcher = $this->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcherInterface')->getMock();

        return new FormConfigBuilder($name, null, $dispatcher);
    }
}
