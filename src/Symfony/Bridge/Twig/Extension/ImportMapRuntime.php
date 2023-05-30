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

    public function importmap(?string $entryPoint = 'app', array $attributes = []): string
    {
        return $this->importMapRenderer->render($entryPoint, $attributes);
    }
}
