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
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Action\OpenUriAction;
use Symfony\Component\Notifier\Exception\InvalidArgumentException;

final class OpenUriActionTest extends TestCase
{
    public function testName()
    {
        $action = (new OpenUriAction())
            ->name($value = 'My name');

        self::assertSame($value, $action->toArray()['name']);
    }

    public function testTargetWithDefaultValue()
    {
        $action = (new OpenUriAction())
            ->target($uri = 'URI');

        self::assertSame([
            ['os' => 'default', 'uri' => $uri],
        ], $action->toArray()['targets']);
    }

    /**
     * @dataProvider operatingSystems
     */
    public function testTarget(string $os)
    {
        $action = (new OpenUriAction())
            ->target($uri = 'URI', $os);

        self::assertSame([
            ['os' => $os, 'uri' => $uri],
        ], $action->toArray()['targets']);
    }

    /**
     * @return \Generator<string, array{0: string}>
     */
    public function operatingSystems(): \Generator
    {
        yield 'os-android' => ['android'];
        yield 'os-default' => ['default'];
        yield 'os-ios' => ['iOS'];
        yield 'os-windows' => ['windows'];
    }

    public function testTargetThrowsWithUnknownOperatingSystem()
    {
        self::expectException(InvalidArgumentException::class);

        (new OpenUriAction())->target('URI', 'FOO');
    }

    public function testToArray()
    {
        self::assertSame([
            '@type' => 'OpenUri',
        ], (new OpenUriAction())->toArray());
    }
}
