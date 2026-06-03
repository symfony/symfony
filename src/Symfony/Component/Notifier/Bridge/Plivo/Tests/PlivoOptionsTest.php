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
        $plivoOptions = (new PlivoOptions())
            ->log(true)
            ->method('test_method')
            ->url('test_url')
            ->mediaUrls('test_media_urls')
            ->powerpackUuid('test_powerpack_uuid')
            ->trackable(true)
            ->type('test_type');

        self::assertSame([
            'log' => true,
            'method' => 'test_method',
            'url' => 'test_url',
            'media_urls' => 'test_media_urls',
            'powerpack_uuid' => 'test_powerpack_uuid',
            'trackable' => true,
            'type' => 'test_type',
        ], $plivoOptions->toArray());
    }
}
