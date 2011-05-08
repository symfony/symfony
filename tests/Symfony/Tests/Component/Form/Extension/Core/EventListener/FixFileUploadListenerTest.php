<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Extension\Core\EventListener;

use Symfony\Component\Form\Event\FilterDataEvent;
use Symfony\Component\Form\Extension\Core\EventListener\FixFileUploadListener;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FixFileUploadListenerTest extends \PHPUnit_Framework_TestCase
{
    private $storage;

    private $destination;

    public function setUp()
    {
        $this->storage = $this->getMockBuilder('Symfony\Component\HttpFoundation\File\TemporaryStorage')
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testValidNewlyUploadedFile()
    {
        $passedToken = null;

        $this->storage->expects($this->any())
            ->method('getTempDir')
            ->will($this->returnCallback(function ($token) use (&$passedToken) {
                $passedToken = $token;

                return __DIR__.DIRECTORY_SEPARATOR.'tmp';
            }));

        $file = $this->createUploadedFileMock('randomhash', 'original.jpg', true);
        $file->expects($this->once())
            ->method('move')
            ->with(__DIR__.DIRECTORY_SEPARATOR.'tmp');

        $data = array(
            'file' => $file,
            'token' => '',
            'name' => '',
            'originalName' => '',
        );

        $form = $this->getMock('Symfony\Tests\Component\Form\FormInterface');
        $event = new FilterDataEvent($form, $data);

        $filter = new FixFileUploadListener($this->storage);
        $filter->onBindClientData($event);

        $this->assertEquals(array(
            'file' => $file,
            'name' => 'randomhash',
            'originalName' => 'original.jpg',
            'token' => $passedToken,
        ), $event->getData());
    }

    public function testExistingUploadedFile()
    {
        $test = $this;

        $this->storage->expects($this->any())
            ->method('getTempDir')
            ->will($this->returnCallback(function ($token) use ($test) {
                $test->assertSame('abcdef', $token);

                return __DIR__.DIRECTORY_SEPARATOR.'Fixtures';
            }));

        $data = array(
            'file' => '',
            'token' => 'abcdef',
            'name' => 'randomhash',
            'originalName' => 'original.jpg',
        );

        $form = $this->getMock('Symfony\Tests\Component\Form\FormInterface');
        $event = new FilterDataEvent($form, $data);

        $filter = new FixFileUploadListener($this->storage);
        $filter->onBindClientData($event);

        $this->assertEquals(array(
            'file' => new UploadedFile(
                __DIR__.DIRECTORY_SEPARATOR.'Fixtures'.DIRECTORY_SEPARATOR.'randomhash',
                'original.jpg',
                null,
                null,
                null,
                true // already moved
             ),
            'name' => 'randomhash',
            'originalName' => 'original.jpg',
            'token' => 'abcdef',
        ), $event->getData());
    }

    public function testNullAndExistingFile()
    {
        $existingData = array(
            'file' => new UploadedFile(
                __DIR__.DIRECTORY_SEPARATOR.'Fixtures'.DIRECTORY_SEPARATOR.'randomhash',
                'original.jpg',
                null,
                null,
                null,
                true // already moved
             ),
            'name' => 'randomhash',
            'originalName' => 'original.jpg',
            'token' => 'abcdef',
        );

        $form = $this->getMock('Symfony\Tests\Component\Form\FormInterface');
        $form->expects($this->any())
            ->method('getNormData')
            ->will($this->returnValue($existingData));

        $event = new FilterDataEvent($form, null);

        $filter = new FixFileUploadListener($this->storage);
        $filter->onBindClientData($event);

        $this->assertSame($existingData, $event->getData());
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testExpectNullOrArray()
    {
        $form = $this->getMock('Symfony\Tests\Component\Form\FormInterface');
        $event = new FilterDataEvent($form, 'foobar');

        $filter = new FixFileUploadListener($this->storage);
        $filter->onBindClientData($event);
    }

    private function createUploadedFileMock($name, $originalName, $valid)
    {
        $file = $this->getMockBuilder('Symfony\Component\HttpFoundation\File\UploadedFile')
            ->disableOriginalConstructor()
            ->getMock();
        $file->expects($this->any())
            ->method('getName')
            ->will($this->returnValue($name));
        $file->expects($this->any())
            ->method('getOriginalName')
            ->will($this->returnValue($originalName));
        $file->expects($this->any())
            ->method('isValid')
            ->will($this->returnValue($valid));

        return $file;
    }
}