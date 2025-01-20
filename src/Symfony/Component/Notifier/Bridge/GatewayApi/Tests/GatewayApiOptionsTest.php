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
        $gatewayApiOptions = (new GatewayApiOptions())
            ->class('test_class')
            ->callbackUrl('test_callback_url')
            ->userRef('test_user_ref')
            ->label('test_label');

        self::assertSame([
            'class' => 'test_class',
            'callback_url' => 'test_callback_url',
            'userref' => 'test_user_ref',
            'label' => 'test_label',
        ], $gatewayApiOptions->toArray());
    }
}
