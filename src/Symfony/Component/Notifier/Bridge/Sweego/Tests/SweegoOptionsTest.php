<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Sweego\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Sweego\SweegoOptions;

final class SweegoOptionsTest extends TestCase
{
    public function testSweegoOptions()
    {
        $sweegoOptions = (new SweegoOptions())
            ->bat(true)
            ->campaignId('test_campaign_id')
            ->shortenUrls(true)
            ->shortenWithProtocol(false)
            ->region('RE');

        $this->assertSame([
            'bat' => true,
            'campaign_id' => 'test_campaign_id',
            'shorten_urls' => true,
            'shorten_with_protocol' => false,
            'region' => 'RE',
        ], $sweegoOptions->toArray());
    }
}
