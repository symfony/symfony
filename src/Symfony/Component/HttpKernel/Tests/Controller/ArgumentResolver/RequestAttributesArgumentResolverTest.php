<?php

namespace Symfony\Component\HttpKernel\Tests\Controller\ArgumentResolver;

use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestAttributesArgumentResolver;

/**
 * @author Wouter J <wouter@wouterj.nl>
 */
class RequestAttributesArgumentResolverTest extends \PHPUnit_Framework_TestCase
{
    private $resolver;

    public function setUp()
    {
        $this->resolver = new RequestAttributesArgumentResolver();
    }

    /**
     * @dataProvider provideParameters
     */
    public function testSupports($parameterName, $supported = true)
    {
        $this->assertEquals($supported, $this->resolver->supports($this->getRequestMock(), $this->getReflectionParameterMock($parameterName)));
    }

    public function provideParameters()
    {
        return array(
            array('exists'),
            array('not_exists', false),
        );
    }

    public function testResolvesToRequestAttributeValues()
    {
        $this->assertEquals('value_of_exists', $this->resolver->resolve($this->getRequestMock(), $this->getReflectionParameterMock('exists')));
    }

    private function getRequestMock()
    {
        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');
        $request->attributes = $this->getMock('Symfony\Component\HttpFoundation\ParameterBag');
        $request->attributes->expects($this->any())->method('has')->will($this->returnValueMap(array(
            array('exists', true),
            array('not_exists', false),
        )));
        $request->attributes->expects($this->any())->method('get')->with('exists')->willReturn('value_of_exists');

        return $request;
    }

    private function getReflectionParameterMock($name)
    {
        $reflectionParameter = $this->getMockBuilder('ReflectionParameter')->disableOriginalConstructor()->getMock();
        $reflectionParameter->expects($this->any())->method('getName')->willReturn($name);

        return $reflectionParameter;
    }
}
