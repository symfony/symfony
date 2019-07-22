<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ErrorRenderer\Tests\ErrorRenderer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ErrorRenderer\ErrorRenderer\TxtErrorRenderer;
use Symfony\Component\ErrorRenderer\Exception\FlattenException;

class TxtErrorRendererTest extends TestCase
{
    public function testRender()
    {
        $exception = FlattenException::createFromThrowable(new \RuntimeException('Foo'));
        $expected = '[title] Internal Server Error%A[status] 500%A[detail] Foo%A[1] RuntimeException: Foo%A';

        $this->assertStringMatchesFormat($expected, (new TxtErrorRenderer())->render($exception));
    }
}
