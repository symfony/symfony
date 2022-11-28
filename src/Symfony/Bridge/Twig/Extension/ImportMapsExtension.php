<?php

declare(strict_types=1);

namespace Symfony\Bridge\Twig\Extension;

use Symfony\Component\ImportMaps\ImportMapManager;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
final class ImportMapsExtension extends AbstractExtension
{
    public function __construct(
        private readonly ImportMapManager $importMapManager,
    )
    {
    }

    public function getFunctions(): iterable
    {
        yield new TwigFunction('importmap', [$this, 'importmap'], ['is_safe' => ['html']]);
    }

    public function importmap(bool $polyfill = true): string
    {
        $json = $this->importMapManager->getImportMap();

        $output = <<<HTML
<script type="importmap">
$json
</script>
HTML;

        if ($polyfill) {
            $output .= <<<'HTML'

<!-- ES Module Shims: Import maps polyfill for modules browsers without import maps support (all except Chrome 89+) -->
<script async src="https://ga.jspm.io/npm:es-module-shims@1.6.2/dist/es-module-shims.js" crossorigin="anonymous"></script>
HTML;

        }

        return $output;
    }
}
