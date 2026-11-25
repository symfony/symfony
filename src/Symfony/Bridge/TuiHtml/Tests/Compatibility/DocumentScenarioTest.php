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

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Resource\ResourceLoader;

final class DocumentScenarioTest extends TestCase
{
    public function testFullFixtureMatrixRetainsAllOriginalCases()
    {
        $cases = [];
        foreach (glob(__DIR__.'/../Fixtures/Compat/*.html') as $file) {
            foreach (FixtureVariants::fromHtml(file_get_contents($file)) as [$name, $options]) {
                $id = basename($file, '.html').'.'.$name;
                self::assertArrayNotHasKey($id, $cases);
                $cases[$id] = $options;
            }
        }
        self::assertCount(634, $cases);
        self::assertSame(['keys' => 'f6,enter,down,enter'], $cases['html-select-dropdown.chosen']);
    }

    public function testScenarioUsesTheSameDictionaryAsTheOriginalConformancePath()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><p lang="en" style="width:64px;font-size:16px;line-height:16px;hyphens:auto">hyphenation</p>');
        [$session] = DocumentScenario::render($document, new ResourceLoader(), 40, 12, [], '#e5e5e5');
        $operations = array_filter($session->render()->getDisplayList()->getOperations(), static fn (array $operation): bool => 'text' === $operation['type']);
        self::assertSame(['hyphen-', 'ation'], array_column($operations, 'text'));
        self::assertInstanceOf(\Symfony\Bridge\TuiHtml\Tests\Fixtures\WordListHyphenator::class, $session->getContext()->hyphenator);
        self::assertSame('hyphenation', $document->querySelector('p')->textContent);
    }

    public function testFocusedPickerKeysConfirmWithoutMutatingDefaultSelection()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><select id="fruit"><option>A</option><option>B</option></select>');
        [$session, $lines, $unsupported] = DocumentScenario::render($document, new ResourceLoader(), 40, 12, ['focus' => '#fruit', 'keys' => 'down,down,enter'], '#e5e5e5');
        self::assertSame(['B'], $session->getSelectedValues($document->querySelector('select')));
        self::assertStringContainsString('B', implode('', $lines));
        self::assertFalse($document->querySelectorAll('option')[1]->hasAttribute('selected'));
        self::assertSame([], $unsupported);
    }

    public function testUnsupportedActionsRemainExplicitAndMediaStateIsApplied()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>p{color:light-dark(blue,yellow)}</style><p>Text</p>');
        [$session, , $unsupported] = DocumentScenario::render($document, new ResourceLoader(), 40, 12, ['scheme' => 'light', 'highlight' => 'search:Text', 'text-sizing' => '1', 'unimplemented-action' => '1'], '#e5e5e5');
        self::assertSame('#0000ff', $session->render()->getComputedStyle($document->querySelector('p'))['color']);
        self::assertSame(['unimplemented-action'], $unsupported);
    }

    public function testLegacyComputedColorPolicyIsExplicitAndUsesTheSameDevicePalette()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><p style="color:light-dark(blue,yellow);background-image:linear-gradient(blue,transparent)">Text</p>');
        [$session] = DocumentScenario::render($document, new ResourceLoader(), 40, 12, ['scheme' => 'light'], '#e5e5e5', \Symfony\Bridge\TuiHtml\Render\TerminalColorPalette::lookupStandardName(...));
        self::assertSame('#0000ee', $session->render()->getComputedStyle($document->querySelector('p'))['color']);
        $gradients = array_values(array_filter($session->render()->getDisplayList()->getOperations(), static fn (array $op): bool => 'gradient' === $op['type']));
        self::assertSame([[0.0, 0.0, 238 / 255], 1.0], $gradients[0]['gradient']['stops'][0][0]);
    }
}
