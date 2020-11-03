<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Intl\Locale\Locale as IntlLocale;
use Symfony\Polyfill\Intl\Icu\Locale as LocalePolyfill;

if (!class_exists(LocalePolyfill::class)) {
    trigger_deprecation('symfony/intl', '5.3', 'Polyfills are deprecated, try running "composer require symfony/polyfill-intl-icu ^1.21" instead.');

    /**
     * Stub implementation for the Locale class of the intl extension.
     *
     * @author Bernhard Schussek <bschussek@gmail.com>
     *
     * @see IntlLocale
     */
    class Locale extends IntlLocale
    {
    }
} else {
    /**
     * Stub implementation for the Locale class of the intl extension.
     *
     * @author Bernhard Schussek <bschussek@gmail.com>
     *
     * @see IntlLocale
     */
    class Locale extends LocalePolyfill
    {
    }
}
