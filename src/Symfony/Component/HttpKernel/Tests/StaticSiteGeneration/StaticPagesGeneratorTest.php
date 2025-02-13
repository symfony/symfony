<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\StaticSiteGeneration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\RuntimeException;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\StaticSiteGeneration\StaticPagesGenerator;

class StaticPagesGeneratorTest extends TestCase
{
    public function testGenerateContent()
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $response = new Response('foo-content');

        $kernel->method('handle')
            ->willReturn($response);

        $generator = new StaticPagesGenerator($kernel);
        ['content' => $content, 'format' => $format] = $generator->generate('/whatever');

        $this->assertSame('foo-content', $content);
        $this->assertNull($format);
    }

    public function testThrowOnNotOk()
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $response = new Response('foo-content', 404);

        $kernel->method('handle')
            ->willReturn($response);

        $generator = new StaticPagesGenerator($kernel);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Expected URI "/whatever" to return status code 200, got 404.');
        $generator->generate('/whatever');
    }

    public function testTerminateKernel()
    {
        $response = new Response('foo-content');

        $kernel = $this->createMock(HttpKernel::class);
        $kernel->expects($this->once())
            ->method('terminate');

        $kernel->method('handle')
            ->willReturn($response);

        $generator = new StaticPagesGenerator($kernel);
        $generator->generate('/whatever');
    }
}
