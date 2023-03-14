<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
        private readonly string $polyfillUrl = ImportMapManager::POLYFILL_URL,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('importmap', [$this, 'importmap'], ['is_safe' => ['html']]),
        ];
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
            $output .= <<<HTML

<!-- ES Module Shims: Import maps polyfill for modules browsers without import maps support -->
<script async src="$this->polyfillUrl" crossorigin="anonymous"></script>
HTML;
        }

        return $output;
    }
}
