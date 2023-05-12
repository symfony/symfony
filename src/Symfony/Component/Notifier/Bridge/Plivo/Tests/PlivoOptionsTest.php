<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Plivo\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Plivo\PlivoOptions;

class PlivoOptionsTest extends TestCase
{
    public function testPlivoOptions()
    {
        $plivoOptions = (new PlivoOptions())->setRecipientId('test_recipient')->setLog(true)->setSrc('test_src')->setMethod('test_method')->setUrl('test_url')->setMediaUrls('test_media_urls')->setPowerpackUuid('test_powerpack_uuid')->setTrackable(true)->setType('test_type');

        self::assertSame([
            'log' => true,
            'src' => 'test_src',
            'method' => 'test_method',
            'url' => 'test_url',
            'media_urls' => 'test_media_urls',
            'powerpack_uuid' => 'test_powerpack_uuid',
            'trackable' => true,
            'type' => 'test_type',
        ], $plivoOptions->toArray());
    }
}
