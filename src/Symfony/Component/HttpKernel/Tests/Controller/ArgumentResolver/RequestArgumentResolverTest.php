<?php

namespace Symfony\Component\HttpKernel\Tests\Controller\ArgumentResolver;

use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestArgumentResolver;

/**
 * @author Wouter J <wouter@wouterj.nl>
 */
class RequestArgumentResolverTest extends \PHPUnit_Framework_TestCase
{
    private $resolver;

    protected function setUp()
    {
        $this->resolver = new RequestArgumentResolver();
    }

    /**
     * @dataProvider provideClasses
     */
    public function testSupports($class, $supported = true)
    {
        $this->assertEquals($supported, $this->resolver->supports($this->getRequestMock(), $this->getReflectionParameterMock($class)));
    }

    public function provideClasses()
    {
        return array(
            array('Symfony\Component\HttpFoundation\Request'),
            array('Symfony\Component\BrowerKit\Request', false),
            array('\stdClass', false),
        );
    }

    public function testResolvesToRequest()
    {
        $request = $this->getRequestMock();

        $this->assertEquals($request, $this->resolver->resolve($request, $this->getReflectionParameterMock()));
    }

    private function getRequestMock()
    {
        return $this->getMock('Symfony\Component\HttpFoundation\Request');
    }

    private function getReflectionParameterMock($class = null)
    {
        $reflectionParameter = $this->getMockBuilder('ReflectionParameter')->disableOriginalConstructor()->getMock();

        if (null !== $class) {
            $reflectionClass = $this->getMockBuilder('ReflectionClass')->disableOriginalConstructor()->getMock();
            $reflectionClass->expects($this->any())->method('isInstance')->will($this->returnCallback(function ($obj) use ($class) {
                return is_a($obj, $class);
            }));

            $reflectionParameter->expects($this->any())->method('getClass')->will($this->returnValue($reflectionClass));
        }

        return $reflectionParameter;
    }
}
