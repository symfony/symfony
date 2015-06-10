<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Locale\Stub;

@trigger_error('The '.__NAMESPACE__.'\StubNumberFormatter class is deprecated since version 2.3 and will be removed in 3.0. Use the Symfony\Component\Intl\NumberFormatter\NumberFormatter class instead.', E_USER_DEPRECATED);

use Symfony\Component\Intl\NumberFormatter\NumberFormatter;

/**
 * Alias of {@link \Symfony\Component\Intl\NumberFormatter\NumberFormatter}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated since version 2.3, to be removed in 3.0.
 *             Use {@link \Symfony\Component\Intl\NumberFormatter\NumberFormatter}
 *             instead.
 */
class StubNumberFormatter extends NumberFormatter
{
}
