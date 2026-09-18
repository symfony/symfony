<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Tests\Fixtures\httprecorder\tests;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\Attribute\UseRecord;
use Symfony\Bridge\PhpUnit\HttpRecorder;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\RecorderHttpClient;

class RecordsDirectory extends TestCase
{
    #[UseRecord('shared.har')]
    public function testRelativePathsStartFromTheRecordsDirectory()
    {
        $client = new RecorderHttpClient(new MockHttpClient(static fn () => throw new \LogicException('The network must not be reached.')), new HttpRecorder());

        $this->assertSame('shared', $client->request('GET', 'https://example.com/')->getContent());
    }
}
