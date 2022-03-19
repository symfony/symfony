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

use Symfony\Contracts\Translation\LocaleAwareInterface;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class LocaleSwitcher implements LocaleAwareInterface
{
    /**
     * @param LocaleAwareInterface[] $localeAwareServices
     */
    public function __construct(private string $locale, private iterable $localeAwareServices)
    {
    }

    public function setLocale(string $locale): void
    {
        \Locale::setDefault($this->locale = $locale);

        foreach ($this->localeAwareServices as $service) {
            $service->setLocale($locale);
        }
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Switch to a new locale, execute a callback, then switch back to the original.
     *
     * @param callable():void $callback
     */
    public function runWithLocale(string $locale, callable $callback): void
    {
        $original = $this->getLocale();
        $this->setLocale($locale);

        try {
            $callback();
        } finally {
            $this->setLocale($original);
        }
    }
}
