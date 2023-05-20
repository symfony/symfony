<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\ClickSend\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\ClickSend\ClickSendOptions;

class ClickSendOptionsTest extends TestCase
{
    public function testClickSendOptions()
    {
        $clickSendOptions = (new ClickSendOptions())
            ->country('test_country')
            ->customString('test_custom_string')
            ->fromEmail('test_from_email')
            ->listId('test_list_id')
            ->schedule(999)
            ->source('test_source');

        self::assertSame([
            'country' => 'test_country',
            'custom_string' => 'test_custom_string',
            'from_email' => 'test_from_email',
            'list_id' => 'test_list_id',
            'schedule' => 999,
            'source' => 'test_source',
        ], $clickSendOptions->toArray());
    }
}
