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

@trigger_error('The '.__NAMESPACE__.'\Transformer class is deprecated since Symfony 2.3 and will be removed in 3.0. Use the Symfony\Component\Intl\DateFormatter\DateFormat\Transformer class instead.', E_USER_DEPRECATED);

use Symfony\Component\Intl\DateFormatter\DateFormat\Transformer as BaseTransformer;

/**
 * Alias of {@link \Symfony\Component\Intl\DateFormatter\DateFormat\Transformer}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated since version 2.3, to be removed in 3.0.
 *             Use {@link \Symfony\Component\Intl\DateFormatter\DateFormat\Transformer}
 *             instead.
 */
abstract class Transformer extends BaseTransformer
{
}
