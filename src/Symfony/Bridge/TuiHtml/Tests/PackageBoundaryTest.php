<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Tui;

final class PackageBoundaryTest extends TestCase
{
    public function testCoreDoesNotDependOnHtmlRendering()
    {
        $core = self::tuiDirectory();
        $violations = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($core)) as $file) {
            $path = str_replace('\\', '/', $file->getPathname());
            // Relative to Tui, which may itself be installed in a vendor directory
            if (!$file->isFile() || 'php' !== $file->getExtension() || preg_match('~^/?(vendor|Tests)/~', substr($path, \strlen($core)))) {
                continue;
            }
            if (preg_match('/\\\\Dom\\\\|Symfony\\\\Component\\\\TuiHtml\\\\/', file_get_contents($path))) {
                $violations[] = substr($path, \strlen($core) + 1);
            }
        }
        sort($violations);
        $this->assertSame([], $violations);
    }

    /**
     * The optional libraries are used by the classes that integrate them only, which nothing else refers to, and by
     * classes that check they are installed first: TuiHtml renders HTML without any of them installed (see
     * OptionalDependenciesTest).
     */
    public function testOptionalLibrariesAreUsedByTheirIntegrationsOnly()
    {
        $integrations = [
            'Css/AssetMapperStylesheetLoader.php' => 'AssetMapperStylesheetLoader',
            'Hyphenation/HeiglHyphenator.php' => 'HeiglHyphenator',
            'Twig/TuiExtension.php' => 'TuiExtension',
            'Twig/TuiRenderer.php' => 'TuiRenderer',
            'Twig/DocumentRenderer.php' => 'DocumentRenderer',
        ];
        // <tui-markdown>, drawn as written without league/commonmark
        $guarded = ['Html/Element/MarkdownElement.php'];
        $component = str_replace('\\', '/', \dirname(__DIR__)).'/';
        $using = [];
        $referring = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($component, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $path = str_replace('\\', '/', $file->getPathname());
            $relative = substr($path, \strlen($component));
            if ('php' !== $file->getExtension() || preg_match('~^(Tests|vendor|Resources)/~', $relative)) {
                continue;
            }
            // The code, not what comments say about it
            $source = implode('', array_map(static fn ($token) => \is_array($token) ? (\in_array($token[0], [\T_COMMENT, \T_DOC_COMMENT], true) ? '' : $token[1]) : $token, token_get_all((string) file_get_contents($path))));
            if (preg_match('/\\b(Twig|League\\\\CommonMark|Tempest|Org\\\\Heigl|Symfony\\\\UX|Symfony\\\\Component\\\\AssetMapper)\\\\/', $source)) {
                $using[] = $relative;
            }
            foreach ($integrations as $integration => $class) {
                if ($relative !== $integration && preg_match('/\\b'.$class.'\\b/', $source)) {
                    $referring[] = $relative.' -> '.$class;
                }
            }
        }
        sort($using);
        $expected = [...array_keys($integrations), ...$guarded];
        sort($expected);
        $this->assertSame($expected, $using);
        $this->assertSame([], $referring);
    }

    /**
     * What TuiHtml uses of Tui besides its public API: helpers, which would have to be copied, or made public, if it
     * lived in another package. The contracts of a renderer are public (see Tui's ThirdPartyRendererTest).
     */
    public function testTheInternalsOfTuiUsedAreKnown()
    {
        $this->assertSame([
            'Symfony\Component\Tui\Ansi\AnsiCodeTracker',
            'Symfony\Component\Tui\Ansi\Bidi::direction',
            'Symfony\Component\Tui\Ansi\Bidi::hasRightToLeft',
            'Symfony\Component\Tui\Ansi\Bidi::needsReordering',
            'Symfony\Component\Tui\Ansi\Bidi::openControls',
            'Symfony\Component\Tui\Ansi\Bidi::reorderLine',
            'Symfony\Component\Tui\Ansi\Bidi::resolveLevels',
            'Symfony\Component\Tui\Ansi\Bidi::visualOrder',
            'Symfony\Component\Tui\Input\KeyParser::decodePrintable',
            'Symfony\Component\Tui\Input\Keybindings::getParser',
            'Symfony\Component\Tui\Style\DefaultStyleSheet',
            'Symfony\Component\Tui\Tui::cancelInterval',
            'Symfony\Component\Tui\Tui::scheduleInterval',
            'Symfony\Component\Tui\Widget\AbstractWidget::getChangeSerial',
            'Symfony\Component\Tui\Widget\AbstractWidget::getChildrenChangedSince',
            'Symfony\Component\Tui\Widget\AbstractWidget::getCurrentRenderCache',
            'Symfony\Component\Tui\Widget\Editor\EditorDocument',
            'Symfony\Component\Tui\Widget\Figlet\FigletRenderer',
            'Symfony\Component\Tui\Widget\TabsWidget::renderWithContent',
            'Symfony\Component\Tui\Widget\Util\Line',
            'Symfony\Component\Tui\Widget\Util\StringUtils',
            'Symfony\Component\Tui\Widget\WidgetContext::attachChild',
            'Symfony\Component\Tui\Widget\WidgetContext::detachChild',
        ], self::internalsOfTuiUsed());
    }

    /**
     * The internal methods of Tui whose name TuiHtml declares too: a call "->name(" is not told apart by reading, so
     * each one was checked, and a new one must be.
     *
     *  - AbstractWidget::attach() is used: the renderer attaches to a widget the widgets of the pseudo-elements it draws
     *    over it, which are none of its children;
     *  - Bidi::direction() is called statically, so it is listed in testTheInternalsOfTuiUsedAreKnown();
     *  - the others are called on TuiHtml's own classes only.
     */
    public function testTheInternalMethodsOfTuiNamedLikeOnesOfTuiHtmlWereChecked()
    {
        $this->assertSame([
            'Symfony\Component\Tui\Ansi\Bidi::direction',
            'Symfony\Component\Tui\Style\Border::wrapLines',
            'Symfony\Component\Tui\Style\Style::isPlain',
            'Symfony\Component\Tui\Style\Style::withoutLayoutProperties',
            'Symfony\Component\Tui\Widget\AbstractWidget::attach',
            'Symfony\Component\Tui\Widget\AbstractWidget::detach',
            'Symfony\Component\Tui\Widget\WidgetContext::invalidateStyles',
        ], self::scan()['ambiguous']);
    }

    /**
     * Where the Tui loaded is, the one of the monorepo or the one installed: its files are those its classes come from.
     */
    private static function tuiDirectory(): string
    {
        return str_replace('\\', '/', \dirname((new \ReflectionClass(Tui::class))->getFileName()));
    }

    /**
     * The internal classes of Tui imported, its internal static methods called through an imported class, and its
     * internal methods called by a name TuiHtml does not declare too.
     *
     * @return list<string>
     */
    private static function internalsOfTuiUsed(): array
    {
        return self::scan()['used'];
    }

    /**
     * @return array{used: list<string>, ambiguous: list<string>}
     */
    private static function scan(): array
    {
        $files = static function (string $directory): iterable {
            $directory = str_replace('\\', '/', $directory);
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
                $path = str_replace('\\', '/', $file->getPathname());
                // Relative to the component, which may itself be installed in a vendor directory
                if ('php' === $file->getExtension() && !preg_match('~^/?(Tests|vendor|Resources)/~', substr($path, \strlen($directory)))) {
                    yield $path;
                }
            }
        };
        $tui = self::tuiDirectory().'/';
        $classes = [];
        $methods = [];
        foreach ($files($tui) as $path) {
            $class = 'Symfony\\Component\\Tui\\'.str_replace('/', '\\', substr($path, \strlen($tui), -4));
            if (!class_exists($class) && !interface_exists($class) && !trait_exists($class) && !enum_exists($class)) {
                continue;
            }
            $reflection = new \ReflectionClass($class);
            if (str_contains((string) $reflection->getDocComment(), '@internal')) {
                $classes[$class] = true;
                continue;
            }
            foreach ($reflection->getMethods() as $method) {
                if ($method->class === $class && !$method->isPrivate() && !$method->isConstructor() && str_contains((string) $method->getDocComment(), '@internal')) {
                    $methods[$method->name][] = $class;
                }
            }
        }
        $sources = [];
        $declared = [];
        foreach ($files(\dirname(__DIR__)) as $path) {
            $sources[] = $source = file_get_contents($path);
            preg_match_all('/function\s+(\w+)\s*\(/', $source, $matches);
            $declared += array_fill_keys($matches[1], true);
        }
        $used = [];
        $ambiguous = [];
        foreach ($sources as $source) {
            preg_match_all('/^use\s+(Symfony\\\\Component\\\\Tui\\\\[\w\\\\]+)(?:\s+as\s+(\w+))?;/m', $source, $matches, \PREG_SET_ORDER);
            $imports = [];
            foreach ($matches as $match) {
                $class = $match[1];
                $imports[$match[2] ?? '' ?: substr(strrchr('\\'.$class, '\\'), 1)] = $class;
                if (isset($classes[$class])) {
                    $used[$class] = true;
                }
            }
            // Static calls name their class
            preg_match_all('/\b([A-Z]\w*)::(\w+)\(/', $source, $calls, \PREG_SET_ORDER);
            foreach ($calls as [, $alias, $name]) {
                if (isset($imports[$alias]) && \in_array($imports[$alias], $methods[$name] ?? [], true)) {
                    $used[$imports[$alias].'::'.$name] = true;
                }
            }
            // Instance calls, and calls to an inherited method, for the names TuiHtml does not declare
            foreach ($methods as $name => $owners) {
                if (!isset($declared[$name]) && preg_match('/(->|\b(?:parent|self|static)::)'.$name.'\(/', $source)) {
                    foreach ($owners as $owner) {
                        $used[$owner.'::'.$name] = true;
                    }
                }
            }
        }
        foreach ($methods as $name => $owners) {
            foreach (isset($declared[$name]) ? $owners : [] as $owner) {
                $ambiguous[$owner.'::'.$name] = true;
            }
        }
        ksort($used);
        ksort($ambiguous);

        return ['used' => array_keys($used), 'ambiguous' => array_keys($ambiguous)];
    }
}
