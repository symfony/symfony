<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Intl\Collator\Collator as IntlCollator;
use Symfony\Component\Intl\Collator\StubCollator;

/**
 * Stub implementation for the Collator class of the intl extension.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @see StubCollator
 */
class Collator extends IntlCollator
{
}
