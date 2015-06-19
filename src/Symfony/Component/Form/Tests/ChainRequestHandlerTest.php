<?php

namespace Symfony\Component\Form\Tests;

use Symfony\Component\Form\ChainRequestHandler;

class ChainRequestHandlerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ChainRequestHandler
     */
    private $requestHandler;

    public function setUp()
    {
        $this->requestHandler = new ChainRequestHandler();
    }

    public function testChainsOverRequestHandlerUntilOneSupportsTheRequest()
    {
        $handler1 = $this->createHandlerMock(false);
        $handler1->expects($this->never())->method('handleRequest');

        $handler2 = $this->createHandlerMock();
        $handler2->expects($this->once())->method('handleRequest');

        $handler3 = $this->createHandlerMock();
        $handler3->expects($this->never())->method('handleRequest');

        $this->requestHandler->add($handler1);
        $this->requestHandler->add($handler2);
        $this->requestHandler->add($handler3);

        $this->requestHandler->handleRequest($this->createFormMock());
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\LogicException
     */
    public function testFailsIfNoneOfTheHandlersSupportsTheRequest()
    {
        $this->requestHandler->handleRequest($this->createFormMock());
    }

    private function createFormMock()
    {
        return $this->getMock('Symfony\Component\Form\FormInterface');
    }

    private function createHandlerMock($support = true)
    {
        $handler = $this->getMock('Symfony\Component\Form\ChainableRequestHandlerInterface');
        $handler->expects($this->any())->method('supports')->will($this->returnValue($support));

        return $handler;
    }
}
