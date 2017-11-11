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
 * A reason why a transition cannot be performed for a subject.
 */
class TransitionBlocker
{
    const REASON_TRANSITION_NOT_DEFINED = '80f2a8e9-ee53-408a-9dd8-cce09e031db8';
    const REASON_TRANSITION_NOT_APPLICABLE = '19beefc8-6b1e-4716-9d07-a39bd6d16e34';
    const REASON_TRANSITION_UNKNOWN = 'e8b5bbb9-5913-4b98-bfa6-65dbd228a82a';

    private $message;
    private $code;

    /**
     * @var array This is useful if you would like to pass around the condition values, that
     *            blocked the transition. E.g. for a condition "distance must be larger than
     *            5 miles", you might want to pass around the value of 5.
     */
    private $parameters;

    public function __construct(string $message, string $code, array $parameters = array())
    {
        $this->message = $message;
        $this->code = $code;
        $this->parameters = $parameters;
    }

    /**
     * Create a blocker, that says the transition cannot be made because it is undefined
     * in a workflow.
     *
     * @param string $transitionName
     * @param string $workflowName
     *
     * @return static
     */
    public static function createNotDefined(string $transitionName, string $workflowName): self
    {
        $message = sprintf('Transition "%s" is not defined in workflow "%s".', $transitionName, $workflowName);

        return new static($message, self::REASON_TRANSITION_NOT_DEFINED);
    }

    /**
     * Create a blocker, that says the transition cannot be made because the subject
     * is in wrong place (i.e. status).
     *
     * @param string $transitionName
     *
     * @return static
     */
    public static function createNotApplicable(string $transitionName): self
    {
        $message = sprintf('Transition "%s" cannot be made, because the subject is not in the required place.', $transitionName);

        return new static($message, self::REASON_TRANSITION_NOT_APPLICABLE);
    }

    /**
     * Create a blocker, that says the transition cannot be made because of unknown
     * reason.
     *
     * This blocker code is chiefly for preserving backwards compatibility.
     *
     * @param string $transitionName
     *
     * @return static
     */
    public static function createUnknownReason(string $transitionName): self
    {
        $message = sprintf('Transition "%s" cannot be made, because of unknown reason.', $transitionName);

        return new static($message, self::REASON_TRANSITION_UNKNOWN);
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }
}
