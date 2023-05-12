<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\GatewayApi\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\GatewayApi\GatewayApiOptions;

class GatewayApiOptionsTest extends TestCase
{
    public function testGatewayApiOptions()
    {
        $gatewayApiOptions = (new GatewayApiOptions())->setFrom('test_from')->setClass('test_class')->setCallbackUrl('test_callback_url')->setUserRef('test_user_ref')->setRecipientId('test_recipient');

        self::assertSame([
            'from' => 'test_from',
            'class' => 'test_class',
            'callback_url' => 'test_callback_url',
            'user_ref' => 'test_user_ref',
        ], $gatewayApiOptions->toArray());
    }
}
