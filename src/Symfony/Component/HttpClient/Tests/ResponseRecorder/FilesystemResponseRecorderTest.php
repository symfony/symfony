<?php
/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests\ResponseRecorder;

use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Filesystem\Tests\FilesystemTestCase;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\ResponseRecorder\FilesystemResponseRecorder;
use Symfony\Contracts\HttpClient\ResponseInterface;

class FilesystemResponseRecorderTest extends FilesystemTestCase
{
    /**
     * @var FilesystemResponseRecorder
     */
    private $recorder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->recorder = new FilesystemResponseRecorder($this->workspace, $this->filesystem);
    }

    public function testReplay(): void
    {
        /** @var ResponseInterface|MockObject $mock */
        $mock = $this->createMock(ResponseInterface::class);
        $mock->method('getContent')->willReturn('Some nice content');
        $mock->method('getInfo')->willReturn(['foo' => 'bar']);

        $this->recorder->record('whatever', $mock);

        $this->assertFileExists($this->workspace.\DIRECTORY_SEPARATOR.'whatever.txt', 'A file should be created');

        $response = $this->recorder->replay('whatever');
        $this->assertNotNull($response, 'Response should be retrieved');
        $this->assertInstanceOf(MockResponse::class, $response, 'Replay should return a MockResponse');

        // MockResponse instances must be issued by MockHttpClient before processing, so content is not yet accessible.
        $ref = new \ReflectionClass($response);
        $body = $ref->getProperty('body');
        $body->setAccessible(true);

        $this->assertSame('Some nice content', $body->getValue($response));
        $this->assertSame('bar', $response->getInfo('foo'));

        $this->assertNull($this->recorder->replay('something_else'), 'Replay should return null here.');
    }
}
