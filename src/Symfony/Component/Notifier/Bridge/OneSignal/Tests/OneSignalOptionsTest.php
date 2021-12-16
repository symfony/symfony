<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\OneSignal\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\OneSignal\OneSignalOptions;

final class OneSignalOptionsTest extends TestCase
{
    public function testOneSignalOptions()
    {
        $oneSignalOptions = (new OneSignalOptions())
            ->headings(['en' => 'English Heading', 'fr' => 'French Heading'])
            ->contents(['en' => 'English Content', 'fr' => 'French Content'])
            ->url('https://example.com')
            ->data(['foo' => 'bar'])
            ->sendAfter(new \DateTimeImmutable('Thu Sep 24 2015 14:00:00 GMT-0700 (PDT)'))
            ->externalId('d637f30d-f709-4bed-9e2c-63637cb91894');

        $this->assertSame([
            'headings' => ['en' => 'English Heading', 'fr' => 'French Heading'],
            'contents' => ['en' => 'English Content', 'fr' => 'French Content'],
            'url' => 'https://example.com',
            'data' => ['foo' => 'bar'],
            'send_after' => '2015-09-24 14:00:00-0700',
            'external_id' => 'd637f30d-f709-4bed-9e2c-63637cb91894',
        ], $oneSignalOptions->toArray());
    }
}
