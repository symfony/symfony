<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Contracts\Translation;

/**
 * Implementation of the ICU recommendation to first format advanced parameters before translation.
 *
 * @see https://unicode-org.github.io/icu/userguide/format_parse/messages/#format-the-parameters-separately-recommended
 *
 * @author Sylvain Fabre <syl.fabre@gmail.com>
 */
interface ParameterInterface
{
    public function format(string $locale = null): string;
}
