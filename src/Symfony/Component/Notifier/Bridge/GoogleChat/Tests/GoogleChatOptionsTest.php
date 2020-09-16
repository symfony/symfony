<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\GoogleChat\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\GoogleChat\GoogleChatOptions;

class GoogleChatOptionsTest extends TestCase
{
    public function testToArray()
    {
        $options = new GoogleChatOptions();

        $options
            ->text('Pizza Bot')
            ->card(['header' => ['Pizza Bot Customer Support']]);

        $expected = [
            'text' => 'Pizza Bot',
            'cards' => [
                ['header' => ['Pizza Bot Customer Support']],
            ],
        ];

        $this->assertSame($expected, $options->toArray());
    }

    public function testOptionsWithThread()
    {
        $thread = 'fgh.ijk';
        $options = new GoogleChatOptions();
        $options->setThreadKey($thread);
        $this->assertSame($thread, $options->getThreadKey());
        $options->setThreadKey(null);
        $this->assertNull($options->getThreadKey());
    }
}
