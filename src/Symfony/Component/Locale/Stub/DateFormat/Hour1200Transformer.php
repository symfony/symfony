<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Locale\Stub\DateFormat;

@trigger_error('The '.__NAMESPACE__.'\Hour1200Transformer class is deprecated since version 2.3 and will be removed in 3.0. Use the Symfony\Component\Intl\DateFormatter\DateFormat\Hour1200Transformer class instead.', E_USER_DEPRECATED);

use Symfony\Component\Intl\DateFormatter\DateFormat\Hour1200Transformer as BaseHour1200Transformer;

/**
 * Alias of {@link \Symfony\Component\Intl\DateFormatter\DateFormat\Hour1200Transformer}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated since version 2.3, to be removed in 3.0.
 *             Use {@link \Symfony\Component\Intl\DateFormatter\DateFormat\Hour1200Transformer}
 *             instead.
 */
class Hour1200Transformer extends BaseHour1200Transformer
{
}
