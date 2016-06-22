<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\HttpFoundation;

use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationRequestHandler;
use Symfony\Component\Form\Tests\AbstractRequestHandlerTest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class HttpFoundationRequestHandlerTest extends AbstractRequestHandlerTest
{
    /**
     * @expectedException \Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testRequestShouldNotBeNull()
    {
        $this->requestHandler->handleRequest($this->getMockForm('name', 'GET'));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testRequestShouldBeInstanceOfRequest()
    {
        $this->requestHandler->handleRequest($this->getMockForm('name', 'GET'), new \stdClass());
    }

    protected function setRequestData($method, $data, $files = array())
    {
        $this->request = Request::create('http://localhost', $method, $data, array(), $files);
    }

    protected function getRequestHandler()
    {
        return new HttpFoundationRequestHandler($this->serverParams);
    }

    protected function getMockFile($suffix = '')
    {
        return new UploadedFile(__DIR__.'/../../Fixtures/foo'.$suffix, 'foo'.$suffix);
    }
}
