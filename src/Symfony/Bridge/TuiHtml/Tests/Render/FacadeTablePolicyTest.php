<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Render;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Tests\Compatibility\FacadeScenario;
use Symfony\Component\Tui\Ansi\AnsiUtils;

class FacadeTablePolicyTest extends TestCase
{
    #[DataProvider('provideTableSpacing')]
    public function testTerminalTableSpacingRemainsAnOverridableUserAgentPolicy(string $css, string $expected, array $options)
    {
        $output = FacadeScenario::render('<style>'.$css.'</style><table><tr><td>A</td><td>B</td></tr></table>', $options + ['columns' => 16, 'rows' => 6], true);

        self::assertStringContainsString($expected, AnsiUtils::stripAnsiCodes(str_replace('⟨', "\x1b[", $output)));
    }

    public static function provideTableSpacing(): iterable
    {
        yield 'default' => ['', 'A B', []];
        yield 'double density' => ['', 'A B', ['dppx' => 2]];
        yield 'custom terminal spacing' => ['table{--tui-cell-spacing:2ch}', 'A  B', []];
        yield 'author override' => ['table{border-spacing:0}', 'AB', []];
    }
}
