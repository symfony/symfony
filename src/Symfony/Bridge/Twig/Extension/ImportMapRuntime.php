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

use Symfony\Component\AssetMapper\ImportMap\ImportMapRenderer;

/**
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
class ImportMapRuntime
{
    public function __construct(private readonly ImportMapRenderer $importMapRenderer)
    {
    }

    public function importmap(string|array|null $entryPoint = 'app', array $attributes = []): string
    {
        if (null === $entryPoint) {
            trigger_deprecation('symfony/twig-bridge', '6.4', 'Passing null as the first argument of the "importmap" Twig function is deprecated, pass an empty array if no entrypoints are desired.');
        }

        return $this->importMapRenderer->render($entryPoint, $attributes);
    }
}
