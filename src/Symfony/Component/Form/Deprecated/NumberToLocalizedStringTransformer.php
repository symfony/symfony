<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Deprecated;

trigger_error('Constants ROUND_HALFEVEN, ROUND_HALFUP and ROUND_HALFDOWN on class NumberToLocalizedStringTransformer were deprecated in Symfony 2.4 and will be removed in 3.0. Use ROUND_HALF_EVEN, ROUND_HALF_UP and ROUND_HALF_DOWN instead.', E_USER_DEPRECATED);

/**
 * @deprecated since 2.7, to be removed in 3.0.
 * @internal
 */
final class NumberToLocalizedStringTransformer
{
    const ROUND_HALFEVEN = \NumberFormatter::ROUND_HALFEVEN;
    const ROUND_HALFUP = \NumberFormatter::ROUND_HALFUP;
    const ROUND_HALFDOWN = \NumberFormatter::ROUND_HALFDOWN;

    private function __construct()
    {
    }
}
