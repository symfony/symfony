<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow;

/**
 * @author Antonio Pauletich <antonio.pauletich95@gmail.com>
 */
enum WorkflowType: string
{
    case Workflow = 'workflow';
    case StateMachine = 'state_machine';
}
