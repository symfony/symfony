<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Layout;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Font\MonospaceTextMeasurer;
use Symfony\Component\HtmlRenderer\Layout\Box;
use Symfony\Component\HtmlRenderer\Layout\LayoutEngine;
use Symfony\Component\HtmlRenderer\Paint\Painter;

final class FirstLineLayoutTest extends TestCase
{
    public function testLogicalLineIdentitySurvivesPaddingAndRelativeTranslation()
    {
        $candidate = ['color' => 'gray', '-renderer-first-line-origin' => '1', '-renderer-first-line-color' => 'green'];
        $box = new Box(null, 'block', ['width' => '100px', '-renderer-first-line-root' => '1'], [new Box(null, 'inline', ['padding-top' => '4px', 'position' => 'relative', 'top' => '8px'], [new Box(null, 'inline', $candidate, [], 'A')]), new Box(null, 'inline', $candidate, [], 'B')]);
        $operations = $this->text($box);
        self::assertSame(['green', 'green'], array_column($operations, 'color'));
    }

    public function testNestedExplicitBreakEndsFirstLineForFollowingSiblings()
    {
        $candidate = ['color' => 'gray', '-renderer-first-line-origin' => '1', '-renderer-first-line-color' => 'green'];
        $box = new Box(null, 'block', ['width' => '100px', '-renderer-first-line-root' => '1'], [new Box(null, 'inline', [], [new Box(null, 'inline', $candidate, [], 'A'), new Box(null, 'line-break'), new Box(null, 'inline', $candidate, [], 'B')]), new Box(null, 'inline', $candidate, [], 'C')]);
        self::assertSame(['green', 'gray', 'gray'], array_column($this->text($box), 'color'));
    }

    private function text(Box $box): array
    {
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 200, 100);

        return array_values(array_filter((new Painter())->paint($layout)->getOperations(), static fn (array $op) => 'text' === $op['type']));
    }
}
