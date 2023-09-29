<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\ImportMap;

use Symfony\Component\Asset\Packages;

/**
 * @author Kévin Dunglas <kevin@dunglas.dev>
 * @author Ryan Weaver <ryan@symfonycasts.com>
 *
 * @final
 */
class ImportMapRenderer
{
    public function __construct(
        private readonly ImportMapManager $importMapManager,
        private readonly ?Packages $assetPackages = null,
        private readonly string $charset = 'UTF-8',
        private readonly string|false $polyfillUrl = ImportMapManager::POLYFILL_URL,
        private readonly array $scriptAttributes = [],
    ) {
    }

    public function render(string|array $entryPoint, array $attributes = []): string
    {
        $entryPoint = (array) $entryPoint;

        $importMapData = $this->importMapManager->getImportMapData($entryPoint);
        $importMap = [];
        $modulePreloads = [];
        $cssLinks = [];
        foreach ($importMapData as $importName => $data) {
            $path = $data['path'];

            if ($this->assetPackages) {
                // ltrim so the subdirectory (if needed) can be prepended
                $path = $this->assetPackages->getUrl(ltrim($path, '/'));
            }

            $preload = $data['preload'] ?? false;
            if ('css' !== $data['type']) {
                $importMap[$importName] = $path;
                if ($preload) {
                    $modulePreloads[] = $path;
                }
            } elseif ($preload) {
                $cssLinks[] = $path;
                // importmap entry is a noop
                $importMap[$importName] = 'data:application/javascript,';
            } else {
                $importMap[$importName] = 'data:application/javascript,'.rawurlencode(sprintf('const d=document,l=d.createElement("link");l.rel="stylesheet",l.href="%s",(d.head||d.getElementsByTagName("head")[0]).appendChild(l)', $path));
            }
        }

        $output = '';
        foreach ($cssLinks as $url) {
            $url = $this->escapeAttributeValue($url);

            $output .= "\n<link rel=\"stylesheet\" href=\"$url\">";
        }

        $scriptAttributes = $this->createAttributesString($attributes);
        $importMapJson = json_encode(['imports' => $importMap], \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_HEX_TAG);
        $output .= <<<HTML
            <script type="importmap"$scriptAttributes>
            $importMapJson
            </script>
            HTML;

        if ($this->polyfillUrl) {
            $url = $this->escapeAttributeValue($this->polyfillUrl);

            $output .= <<<HTML

                <!-- ES Module Shims: Import maps polyfill for modules browsers without import maps support -->
                <script async src="$url"$scriptAttributes></script>
                HTML;
        }

        foreach ($modulePreloads as $url) {
            $url = $this->escapeAttributeValue($url);

            $output .= "\n<link rel=\"modulepreload\" href=\"$url\">";
        }

        if (\count($entryPoint) > 0) {
            $output .= "\n<script type=\"module\"$scriptAttributes>";
            foreach ($entryPoint as $entryPointName) {
                $entryPointName = $this->escapeAttributeValue($entryPointName);

                $output .= "import '".str_replace("'", "\\'", $entryPointName)."';";
            }
            $output .= '</script>';
        }

        return $output;
    }

    private function escapeAttributeValue(string $value): string
    {
        return htmlspecialchars($value, \ENT_COMPAT | \ENT_SUBSTITUTE, $this->charset);
    }

    private function createAttributesString(array $attributes): string
    {
        $attributeString = '';

        $attributes += $this->scriptAttributes;
        if (isset($attributes['src']) || isset($attributes['type'])) {
            throw new \InvalidArgumentException(sprintf('The "src" and "type" attributes are not allowed on the <script> tag rendered by "%s".', self::class));
        }

        foreach ($attributes as $name => $value) {
            $attributeString .= ' ';
            if (true === $value) {
                $attributeString .= $name;

                continue;
            }
            $attributeString .= sprintf('%s="%s"', $name, $this->escapeAttributeValue($value));
        }

        return $attributeString;
    }
}
