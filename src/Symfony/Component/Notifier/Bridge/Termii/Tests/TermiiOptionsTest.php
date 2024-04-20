<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Termii\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Termii\TermiiOptions;

class TermiiOptionsTest extends TestCase
{
    public function testTermiiOptions()
    {
        $termiiOptions = (new TermiiOptions())
            ->type('test_type')
            ->channel('test_channel')
            ->media('test_media_url', 'test_media_caption');

        self::assertSame([
            'type' => 'test_type',
            'channel' => 'test_channel',
            'media' => [
                'url' => 'test_media_url',
                'caption' => 'test_media_caption',
            ],
        ], $termiiOptions->toArray());
    }
}
