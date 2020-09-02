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

use Symfony\Component\Workflow\DependencyInjection\ValidateWorkflowsPass as BaseValidateWorkflowsPass;

@trigger_error(sprintf('The %s class is deprecated since Symfony 3.3 and will be removed in 4.0. Use %s instead.', ValidateWorkflowsPass::class, BaseValidateWorkflowsPass::class), \E_USER_DEPRECATED);

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * @deprecated since version 3.3, to be removed in 4.0. Use {@link BaseValidateWorkflowsPass} instead
 */
class ValidateWorkflowsPass extends BaseValidateWorkflowsPass
{
}
