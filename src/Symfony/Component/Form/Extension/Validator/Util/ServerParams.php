<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Validator\Util;

use Symfony\Component\Form\Util\ServerParams as BaseServerParams;

trigger_deprecation('symfony/form', '5.1', 'The "%s" class is deprecated. Use "%s" instead.', ServerParams::class, BaseServerParams::class);

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated since Symfony 5.1. Use {@see BaseServerParams} instead.
 */
class ServerParams extends BaseServerParams
{
}
