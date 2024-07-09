<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Primotexto\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Primotexto\PrimotextoOptions;

class PrimotextoOptionsTest extends TestCase
{
    public function testPrimotextoOptions()
    {
        $primotextoOptions = (new PrimotextoOptions())
            ->campaignDate(1714121739)
            ->category('test_category')
            ->campaignName('test_campaign_name');

        self::assertSame([
            'date' => 1714121739,
            'category' => 'test_category',
            'campaignName' => 'test_campaign_name',
        ], $primotextoOptions->toArray());
    }
}
