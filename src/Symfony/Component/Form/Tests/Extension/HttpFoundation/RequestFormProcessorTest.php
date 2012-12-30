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

use Symfony\Component\Form\Extension\HttpFoundation\RequestFormProcessor;
use Symfony\Component\Form\Tests\AbstractFormProcessorTest;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RequestFormProcessorTest extends AbstractFormProcessorTest
{
    /**
     * @expectedException \Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testRequestShouldNotBeNull()
    {
        $this->processor->processForm($this->getMockForm('name', 'GET'));
    }
    /**
     * @expectedException \Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testRequestShouldBeInstanceOfRequest()
    {
        $this->processor->processForm($this->getMockForm('name', 'GET'), new \stdClass());
    }

    protected function setRequestData($method, $data, $files = array())
    {
        $this->request = Request::create('http://localhost', $method, $data, array(), $files);
    }

    protected function getFormProcessor()
    {
        return new RequestFormProcessor();
    }

    protected function getMockFile()
    {
        return $this->getMockBuilder('Symfony\Component\HttpFoundation\File\UploadedFile')
            ->disableOriginalConstructor()
            ->getMock();
    }
}
