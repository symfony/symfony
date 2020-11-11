<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Intl\DateFormatter\IntlDateFormatter as BaseIntlDateFormatter;
use Symfony\Polyfill\Intl\Icu\IntlDateFormatter as IntlDateFormatterPolyfill;

if (!class_exists(IntlDateFormatterPolyfill::class)) {
    trigger_deprecation('symfony/intl', '5.3', 'Polyfills are deprecated, try running "composer require symfony/polyfill-intl-icu ^1.21" instead.');

    /**
     * Stub implementation for the IntlDateFormatter class of the intl extension.
     *
     * @author Bernhard Schussek <bschussek@gmail.com>
     *
     * @see BaseIntlDateFormatter
     */
    class IntlDateFormatter extends BaseIntlDateFormatter
    {
    }
} else {
    /**
     * Stub implementation for the IntlDateFormatter class of the intl extension.
     *
     * @author Bernhard Schussek <bschussek@gmail.com>
     *
     * @see BaseIntlDateFormatter
     */
    class IntlDateFormatter extends IntlDateFormatterPolyfill
    {
    }
}
