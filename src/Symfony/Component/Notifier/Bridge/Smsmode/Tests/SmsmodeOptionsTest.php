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
        $smsmodeOptions = (new SmsmodeOptions())->setFrom('test_from')->setRecipientId('test_recipient')->setRefClient('test_ref_client')->setSentDate('test_sent_date');

        self::assertSame([
            'from' => 'test_from',
            'ref_client' => 'test_ref_client',
            'sent_date' => 'test_sent_date',
        ], $smsmodeOptions->toArray());
    }
}
