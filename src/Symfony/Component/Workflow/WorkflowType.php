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
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
enum WorkflowType: string
{
    /**
     * The subject can be in one and only one place at the same time.
     */
    case StateMachine = 'state_machine';

    /**
     * The subject can be in many places at the same time.
     */
    case Workflow = 'workflow';
}
