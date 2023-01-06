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

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * @author Jesse Rushlow <jr@rushlow.dev>
 */
final class SerializerExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('serialize', [SerializerRuntime::class, 'serialize']),
        ];
    }
}
