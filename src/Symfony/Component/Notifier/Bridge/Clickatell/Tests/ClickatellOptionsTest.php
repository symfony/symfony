<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Clickatell\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Clickatell\ClickatellOptions;

class ClickatellOptionsTest extends TestCase
{
    public function testClickatellOptions()
    {
        $clickatellOptions = (new ClickatellOptions())->setFrom('test_from')->setRecipientId('test_recipient');

        self::assertSame(['from' => 'test_from'], $clickatellOptions->toArray());
    }
}
