<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\OrangeSms\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\OrangeSms\OrangeSmsOptions;

class OrangeSmsOptionsTest extends TestCase
{
    public function testOrangeSmsOptions()
    {
        $orangeSmsOptions = (new OrangeSmsOptions())->setFrom('test_from')->setRecipientId('test_recipient');

        self::assertSame([
            'from' => 'test_from',
        ], $orangeSmsOptions->toArray());
    }
}
