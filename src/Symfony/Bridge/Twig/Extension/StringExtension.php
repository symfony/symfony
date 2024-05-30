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
 * Twig extension for the string helper.
 *
 * @author Mathieu Santostefano <msantostefano@protonmail.com>
 */
final class StringExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('pluralize', [StringRuntime::class, 'pluralize'], ['is_safe' => ['html']]),
            new TwigFilter('singularize', [StringRuntime::class, 'singularize'], ['is_safe' => ['html']]),
        ];
    }
}
