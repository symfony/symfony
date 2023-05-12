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
        $ringCentralOptions = (new RingCentralOptions())->setFrom('test_from')->setRecipientId('test_recipient')->setCountryId('test_country_id')->setCountryName('test_country_name')->setCountryUri('test_country_uri')->setCountryCallingCode('test_country_calling_code')->setCountryIsoCode('test_country_iso_code');

        self::assertSame([
            'from' => 'test_from',
            'country_id' => 'test_country_id',
            'country_name' => 'test_country_name',
            'country_uri' => 'test_country_uri',
            'country_calling_code' => 'test_country_calling_code',
            'country_iso_code' => 'test_country_iso_code',
        ], $ringCentralOptions->toArray());
    }
}
