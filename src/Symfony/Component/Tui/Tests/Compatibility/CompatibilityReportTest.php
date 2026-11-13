<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Compatibility;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Compatibility\CompatibilityReport;
use Symfony\Component\Tui\Compatibility\Support;
use Symfony\Component\Tui\Compatibility\SupportProviderInterface;
use Symfony\Component\Tui\Html\ElementRegistry;
use Symfony\Component\Tui\Widget\TextWidget;

final class CompatibilityReportTest extends TestCase
{
    private const MATRIX = __DIR__.'/../../Resources/compatibility.json';

    public function testTheCheckedInMatrixIsUpToDate()
    {
        $json = CompatibilityReport::createDefault()->toJson();

        if (getenv('TUI_UPDATE_COMPAT')) {
            file_put_contents(self::MATRIX, $json);
        }

        $this->assertFileExists(self::MATRIX);
        $this->assertSame(file_get_contents(self::MATRIX), $json, 'Run the tests with TUI_UPDATE_COMPAT=1 to update Resources/compatibility.json.');
    }

    public function testApplicationRegistrationsAreReported()
    {
        $registry = (new ElementRegistry(false))->register('app-tree', static fn () => new TextWidget(), Support::partial('No drag and drop.'));

        $features = (new CompatibilityReport([$registry]))->getFeatures();

        $this->assertSame('partial', $features['html.elements.app-tree']->level->value);
        $this->assertSame('No drag and drop.', $features['html.elements.app-tree']->note);
    }

    public function testLaterProvidersWin()
    {
        $first = new class implements SupportProviderInterface {
            public function getSupport(): array
            {
                return ['css.properties.color' => Support::supported()];
            }
        };
        $second = new class implements SupportProviderInterface {
            public function getSupport(): array
            {
                return ['css.properties.color' => Support::partial('Override.')];
            }
        };

        $this->assertSame('Override.', (new CompatibilityReport([$first, $second]))->getFeatures()['css.properties.color']->note);
    }

    public function testMarkdown()
    {
        $markdown = (new CompatibilityReport([(new ElementRegistry(false))->register('x-a', static fn () => new TextWidget(), Support::partial('a | b'))]))->toMarkdown();

        $this->assertStringContainsString('| `html.elements.x-a` | partial | a \| b |', $markdown);
    }
}
