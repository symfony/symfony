<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\Tests\Extension\HttpFoundation;

use Symphony\Component\Form\Extension\HttpFoundation\HttpFoundationRequestHandler;
use Symphony\Component\Form\Tests\AbstractRequestHandlerTest;
use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\HttpFoundation\File\UploadedFile;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class HttpFoundationRequestHandlerTest extends AbstractRequestHandlerTest
{
    /**
     * @expectedException \Symphony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testRequestShouldNotBeNull()
    {
        $this->requestHandler->handleRequest($this->getMockForm('name', 'GET'));
    }

    /**
     * @expectedException \Symphony\Component\Form\Exception\UnexpectedTypeException
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

    protected function getInvalidFile()
    {
        return 'file:///etc/passwd';
    }
}
