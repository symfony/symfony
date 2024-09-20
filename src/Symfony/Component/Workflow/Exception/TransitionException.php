<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Exception;

use Symfony\Component\Workflow\WorkflowInterface;

/**
 * @author Andrew Tch <andrew.tchircoff@gmail.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class TransitionException extends LogicException
{
    public function __construct(
        private object $subject,
        private string $transitionName,
        private WorkflowInterface $workflow,
        string $message,
        private array $context = [],
    ) {
        parent::__construct($message);
    }

    public function getSubject(): object
    {
        return $this->subject;
    }

    public function getTransitionName(): string
    {
        return $this->transitionName;
    }

    public function getWorkflow(): WorkflowInterface
    {
        return $this->workflow;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
