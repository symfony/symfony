<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests;

use PHPUnit\Framework\TestCase;

final class PackageBoundaryTest extends TestCase
{
    public function testProductionCodeHasNoPlatformOrTemplateDependency()
    {
        $violations = [];
        $root = \dirname(__DIR__);
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $relative = str_replace('\\', '/', substr($file->getPathname(), \strlen($root) + 1));
            if ('php' !== $file->getExtension() || preg_match('~^(Tests|vendor)/~', $relative)) {
                continue;
            }
            $source = implode('', array_map(static fn ($token) => \is_array($token) ? (\in_array($token[0], [\T_COMMENT, \T_DOC_COMMENT], true) ? '' : $token[1]) : $token, token_get_all(file_get_contents($file->getPathname()))));
            if (preg_match('~Symfony\\\\(?:Component\\\\Tui|Bridge\\\\TuiHtml|Bundle\\\\FrameworkBundle)|Twig\\\\|\\\\e\\[|\\\\x1[bB]|data-tui-~', $source)) {
                $violations[] = $relative;
            }
        }
        self::assertSame([], $violations);
        $package = json_decode(file_get_contents($root.'/composer.json'), true, 512, \JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('php', $package['require']);
        self::assertArrayHasKey('ext-dom', $package['require']);
        self::assertSame([], array_filter(array_keys($package['require']), static fn (string $dependency): bool => str_starts_with($dependency, 'symfony/tui') || str_starts_with($dependency, 'twig/')));
    }

    public function testStandaloneBootstrapDoesNotLoadAnotherSymfonyComponent()
    {
        $script = tempnam(sys_get_temp_dir(), 'html-renderer-');
        file_put_contents($script, '<?php require '.var_export(__DIR__.'/bootstrap.php', true).'; $r = new Symfony\\Component\\HtmlRenderer\\HtmlRenderer(); $r->render("<p>Hello</p>", new Symfony\\Component\\HtmlRenderer\\RenderingContext(100, 100)); foreach (get_declared_classes() as $c) { if (str_starts_with($c, "Symfony\\\\") && !str_starts_with($c, "Symfony\\\\Component\\\\HtmlRenderer\\\\")) { echo $c, "\\n"; } }');
        try {
            exec(escapeshellarg(\PHP_BINARY).' '.escapeshellarg($script).' 2>&1', $output, $status);
            self::assertSame(0, $status, implode("\n", $output));
            self::assertSame([], $output);
        } finally {
            unlink($script);
        }
    }
}
