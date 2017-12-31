<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

use Symfony\Component\Validator\DependencyInjection\AddConstraintValidatorsPass as BaseAddConstraintValidatorsPass;

@trigger_error(sprintf('The %s class is deprecated since Symfony 3.3 and will be removed in 4.0. Use %s instead.', AddConstraintValidatorsPass::class, BaseAddConstraintValidatorsPass::class), E_USER_DEPRECATED);

/**
 * @deprecated since version 3.3, to be removed in 4.0. Use {@link BaseAddConstraintValidatorsPass} instead
 */
class AddConstraintValidatorsPass extends BaseAddConstraintValidatorsPass
{
}
