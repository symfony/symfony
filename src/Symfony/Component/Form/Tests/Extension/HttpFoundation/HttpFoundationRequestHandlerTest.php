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
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class HttpFoundationRequestHandlerTest extends AbstractRequestHandlerTest
{
    public function testRequestShouldNotBeNull()
    {
        $this->expectException('Symfony\Component\Form\Exception\UnexpectedTypeException');
        $this->requestHandler->handleRequest($this->createForm('name', 'GET'));
    }

    public function testRequestShouldBeInstanceOfRequest()
    {
        $this->expectException('Symfony\Component\Form\Exception\UnexpectedTypeException');
        $this->requestHandler->handleRequest($this->createForm('name', 'GET'), new \stdClass());
    }

    protected function setRequestData($method, $data, $files = [])
    {
        $this->request = Request::create('http://localhost', $method, $data, [], $files);
    }

    protected function getRequestHandler()
    {
        return new HttpFoundationRequestHandler($this->serverParams);
    }

    protected function getUploadedFile($suffix = '')
    {
        return new UploadedFile(__DIR__.'/../../Fixtures/foo'.$suffix, 'foo'.$suffix);
    }

    protected function getInvalidFile()
    {
        return 'file:///etc/passwd';
    }

    protected function getFailedUploadedFile($errorCode)
    {
        return new UploadedFile(__DIR__.'/../../Fixtures/foo', 'foo', null, null, $errorCode, true);
    }
}
