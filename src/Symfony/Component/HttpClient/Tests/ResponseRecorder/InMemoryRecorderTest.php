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

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\ResponseRecorder\InMemoryRecorder;
use Symfony\Contracts\HttpClient\ResponseInterface;

class InMemoryRecorderTest extends TestCase
{
    public function testReplay()
    {
        /** @var ResponseInterface $response */
        $response = $this->createMock(ResponseInterface::class);
        $recorder = new InMemoryRecorder();

        $this->assertNull($recorder->replay('foo'), 'Should return NULL when not pre-recorded');

        $recorder->record('foo', $response);
        $this->assertSame($response, $recorder->replay('foo'), 'Should return the same response');

        $recorder->reset();
        $this->assertNull($recorder->replay('foo'), 'Should return NULL after reset');
    }
}
