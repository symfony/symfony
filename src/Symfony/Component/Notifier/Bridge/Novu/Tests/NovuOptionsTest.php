<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Novu\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Novu\NovuOptions;

class NovuOptionsTest extends TestCase
{
    public function testToArray()
    {
        $options = new NovuOptions(
            123,
            'Joe',
            'Smith',
            'test@example.com',
            null,
            null,
            null,
            [
                'email' => [
                    'from' => 'no-reply@example.com',
                    'senderName' => 'No-Reply',
                ],
            ],
            [],
        );

        $this->assertSame(
            [
                'firstName' => 'Joe',
                'lastName' => 'Smith',
                'email' => 'test@example.com',
                'phone' => null,
                'avatar' => null,
                'locale' => null,
                'overrides' => [
                    'email' => [
                        'from' => 'no-reply@example.com',
                        'senderName' => 'No-Reply',
                    ],
                ],
            ],
            $options->toArray()
        );
    }
}
