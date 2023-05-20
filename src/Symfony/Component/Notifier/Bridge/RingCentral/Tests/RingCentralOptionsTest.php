<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\RingCentral\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\RingCentral\RingCentralOptions;

class RingCentralOptionsTest extends TestCase
{
    public function testRingCentralOptions()
    {
        $ringCentralOptions = (new RingCentralOptions())
            ->country('test_country_id', 'test_country_iso_code', 'test_country_name', 'test_country_uri', 'test_country_calling_code');

        self::assertSame([
            'country' => [
                'id' => 'test_country_id',
                'isoCode' => 'test_country_iso_code',
                'name' => 'test_country_name',
                'uri' => 'test_country_uri',
                'callingCode' => 'test_country_calling_code',
            ],
        ], $ringCentralOptions->toArray());
    }
}
