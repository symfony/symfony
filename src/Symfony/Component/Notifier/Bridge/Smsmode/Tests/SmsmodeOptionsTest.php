<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Smsmode\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Smsmode\SmsmodeOptions;

class SmsmodeOptionsTest extends TestCase
{
    public function testSmsmodeOptions()
    {
        $smsmodeOptions = (new SmsmodeOptions())
            ->refClient('test_ref_client')
            ->sentDate('test_sent_date');

        self::assertSame([
            'refClient' => 'test_ref_client',
            'sentDate' => 'test_sent_date',
        ], $smsmodeOptions->toArray());
    }
}
