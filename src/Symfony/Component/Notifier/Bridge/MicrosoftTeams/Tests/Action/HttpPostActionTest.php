<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MicrosoftTeams\Tests\Action;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Action\Element\Header;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Action\HttpPostAction;

final class HttpPostActionTest extends TestCase
{
    public function testName()
    {
        $action = (new HttpPostAction())
            ->name($value = 'My name');

        $this->assertSame($value, $action->toArray()['name']);
    }

    public function testTarget()
    {
        $action = (new HttpPostAction())
            ->target($value = 'https://symfony.com');

        $this->assertSame($value, $action->toArray()['target']);
    }

    public function testHeader()
    {
        $header = (new Header())
            ->name($name = 'Header-Name')
            ->value($value = 'Header-Value');

        $action = (new HttpPostAction())
            ->header($header);

        $this->assertCount(1, $action->toArray()['headers']);
        $this->assertSame(
            [
                ['name' => $name, 'value' => $value],
            ],
            $action->toArray()['headers']
        );
    }

    public function testBody()
    {
        $action = (new HttpPostAction())
            ->body($value = 'content');

        $this->assertSame($value, $action->toArray()['body']);
    }

    public function testBodyContentType()
    {
        $action = (new HttpPostAction())
            ->bodyContentType($value = 'application/json');

        $this->assertSame($value, $action->toArray()['bodyContentType']);
    }

    public function testToArray()
    {
        $this->assertSame(
            [
                '@type' => 'HttpPOST',
            ],
            (new HttpPostAction())->toArray()
        );
    }
}
