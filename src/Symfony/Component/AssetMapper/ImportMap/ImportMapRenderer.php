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

/**
 * @experimental
 *
 * @author Kévin Dunglas <kevin@dunglas.dev>
 * @author Ryan Weaver <ryan@symfonycasts.com>
 *
 * @final
 */
class ImportMapRenderer
{
    public function __construct(
        private readonly ImportMapManager $importMapManager,
        private readonly string $charset = 'UTF-8',
        private readonly string|false $polyfillUrl = ImportMapManager::POLYFILL_URL,
        private readonly array $scriptAttributes = [],
    ) {
    }

    public function render(string $entryPoint = null, array $attributes = []): string
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

        $output = <<<HTML
            <script type="importmap"{$attributeString}>
            {$this->importMapManager->getImportMapJson()}
            </script>
            HTML;

        if ($this->polyfillUrl) {
            $url = $this->escapeAttributeValue($this->polyfillUrl);

            $output .= <<<HTML

                <!-- ES Module Shims: Import maps polyfill for modules browsers without import maps support -->
                <script async src="$url"$attributeString></script>
                HTML;
        }

        foreach ($this->importMapManager->getModulesToPreload() as $url) {
            $url = $this->escapeAttributeValue($url);

            $output .= "\n<link rel=\"modulepreload\" href=\"{$url}\">";
        }

        if (null !== $entryPoint) {
            $output .= "\n<script type=\"module\"$attributeString>import '".str_replace("'", "\\'", $entryPoint)."';</script>";
        }

        return $output;
    }

    private function escapeAttributeValue(string $value): string
    {
        return htmlspecialchars($value, \ENT_COMPAT | \ENT_SUBSTITUTE, $this->charset);
    }
}
