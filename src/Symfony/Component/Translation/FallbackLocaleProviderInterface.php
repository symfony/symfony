<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation;

use Symfony\Component\Translation\Exception\InvalidArgumentException;

/**
 * For a given locale, implementations provide the list of alternative locales to
 * try when a translation cannot be found.
 *
 * @author Matthias Pigulla <mp@webfactory.de>
 */
interface FallbackLocaleProviderInterface
{
    /**
     * @internal
     *
     * @return string[]
     */
    public function getUltimateFallbackLocales(): array;

    /**
     * For a given locale, this method provides the ordered list of alternative (fallback) locales
     * to try.
     *
     * @return string[]
     */
    public function computeFallbackLocales(string $locale): array;
}
