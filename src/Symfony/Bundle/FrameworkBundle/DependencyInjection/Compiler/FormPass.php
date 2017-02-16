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

@trigger_error(sprintf('The %s class is deprecated since version 3.3 and will be removed in 4.0. Use Symfony\Component\Form\DependencyInjection\FormPass instead.', FormPass::class), E_USER_DEPRECATED);

use Symfony\Component\Form\DependencyInjection\FormPass as BaseFormPass;

/**
 * Adds all services with the tags "form.type" and "form.type_guesser" as
 * arguments of the "form.extension" service.
 *
 * @deprecated since version 3.3, to be removed in 4.0. Use {@link BaseFormPass} instead.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormPass extends BaseFormPass
{
}
