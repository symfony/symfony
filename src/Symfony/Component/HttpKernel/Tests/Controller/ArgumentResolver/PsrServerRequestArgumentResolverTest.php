<?php

namespace Symfony\Component\HttpKernel\Tests\Controller\ArgumentResolver;

use Symfony\Component\HttpKernel\Controller\ArgumentResolver\PsrServerRequestArgumentResolver;

/**
 * @author Wouter J <wouter@wouterj.nl>
 */
class PsrServerRequestArgumentResolverTest extends \PHPUnit_Framework_TestCase
{
    private $httpMessageFactory;
    private $resolver;

    protected function setUp()
    {
        $this->httpMessageFactory = $this->getMock('Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface', array('createRequest'));
        $this->resolver = new PsrServerRequestArgumentResolver($this->httpMessageFactory);
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
            array('Psr\Http\Message\ServerRequestInterface'),
            array('Psr\Http\Message\RequestInterface'),
            array('Psr\Http\Message\MessageInterface'),
            array('\stdClass', false),
            array('Symfony\Component\HttpFoundation\Request', false),
        );
    }

    public function testResolvesUsingHttpMessageFactory()
    {
        $request = $this->getRequestMock();

        $this->httpMessageFactory->expects($this->once())->method('createRequest')->with($request);

        $this->resolver->resolve($request, $this->getReflectionParameterMock());
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
            $reflectionClass->expects($this->any())->method('getName')->willReturn($class);

            $reflectionParameter->expects($this->any())->method('getClass')->will($this->returnValue($reflectionClass));
        }

        return $reflectionParameter;
    }
}
