<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Translation;

use Symfony\Component\Translation\Extractor\PhpExtractor as NewPhpExtractor;

@trigger_error(sprintf('The class "%s" is deprecated since Symfony 3.4 and will be removed in 4.0. Use "%s" instead. ', PhpExtractor::class, NewPhpExtractor::class), \E_USER_DEPRECATED);

/**
 * @deprecated since version 3.4 and will be removed in 4.0. Use Symfony\Component\Translation\Extractor\PhpExtractor instead
 */
class PhpExtractor extends NewPhpExtractor
{
}
