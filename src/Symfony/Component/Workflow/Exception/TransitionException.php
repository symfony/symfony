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

/**
 * @author Andrew Tch <andrew.tchircoff@gmail.com>
 */
class TransitionException extends LogicException
{
    /**
     * @var mixed
     */
    private $subject;

    /**
     * @var string
     */
    private $transitionName;

    /**
     * @var string
     */
    private $workflowName;

    public function __construct($subject, $transitionName, $workflowName)
    {
        $this->subject = $subject;
        $this->transitionName = $transitionName;
        $this->workflowName = $workflowName;

        parent::__construct(sprintf('Unable to apply transition "%s" for workflow "%s".', $this->transitionName, $this->workflowName));
    }

    /**
     * @return mixed
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * @return string
     */
    public function getTransitionName()
    {
        return $this->transitionName;
    }

    /**
     * @return string
     */
    public function getWorkflowName()
    {
        return $this->workflowName;
    }
}
