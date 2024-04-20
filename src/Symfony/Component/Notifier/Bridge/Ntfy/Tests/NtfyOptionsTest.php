<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Ntfy\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Ntfy\NtfyOptions;

/**
 * @author Mickael Perraud <mikaelkael.fr@gmail.com>
 */
class NtfyOptionsTest extends TestCase
{
    public function testNtfyOptions()
    {
        $delay = (new \DateTime())->add(new \DateInterval('PT1M'));
        $ntfyOptions = (new NtfyOptions())
            ->setMessage('test message')
            ->setTitle('message title')
            ->setPriority(NtfyOptions::PRIORITY_URGENT)
            ->setTags(['tag1', 'tag2'])
            ->addTag('tag3')
            ->setDelay($delay)
            ->setActions([['action' => 'view', 'label' => 'View', 'url' => 'https://test.com']])
            ->addAction(['action' => 'http', 'label' => 'Open', 'url' => 'https://test2.com'])
            ->setClick('https://test3.com')
            ->setAttachment('https://filesrv.lan/space.jpg')
            ->setFilename('diskspace.jpg')
            ->setEmail('me@mail.com')
            ->setCache(false)
            ->setFirebase(false)
        ;

        $this->assertSame([
            'message' => 'test message',
            'title' => 'message title',
            'priority' => NtfyOptions::PRIORITY_URGENT,
            'tags' => ['tag1', 'tag2', 'tag3'],
            'delay' => (string) $delay->getTimestamp(),
            'actions' => [
                ['action' => 'view', 'label' => 'View', 'url' => 'https://test.com'],
                ['action' => 'http', 'label' => 'Open', 'url' => 'https://test2.com'],
            ],
            'click' => 'https://test3.com',
            'attach' => 'https://filesrv.lan/space.jpg',
            'filename' => 'diskspace.jpg',
            'email' => 'me@mail.com',
            'cache' => 'no',
            'firebase' => 'no',
        ], $ntfyOptions->toArray());
    }
}
