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
        $termiiOptions = (new TermiiOptions())->setFrom('test_from')->setRecipientId('test_recipient')->setType('test_type')->setChannel('test_channel')->setMediaCaption('test_media_caption')->setMediaUrl('test_media_url');

        self::assertSame([
            'from' => 'test_from',
            'type' => 'test_type',
            'channel' => 'test_channel',
            'media_caption' => 'test_media_caption',
            'media_url' => 'test_media_url',
        ], $termiiOptions->toArray());
    }
}
