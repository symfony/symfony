<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Compatibility;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Css\Declaration;
use Symfony\Component\HtmlRenderer\Exception\InvalidArgumentException;

class CoreTypeContractTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testIncomingDeclarationsUseTheCoreTypeContract()
    {
        $consume = static fn (Declaration $declaration): string => $declaration->value;
        self::assertSame('red', $consume(new Declaration('color', 'red')));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testExceptionsUseTheCoreTypeContract()
    {
        try {
            throw new InvalidArgumentException('invalid');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('invalid', $exception->getMessage());
        }
    }
}
