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
    const REASON_CODE_TRANSITION_NOT_DEFINED = 'com.symfony.www.workflow.transition_blocker.not_defined';
    const REASON_CODE_TRANSITION_NOT_APPLICABLE = 'com.symfony.www.workflow.transition_blocker.not_applicable';
    const REASON_CODE_TRANSITION_UNKNOWN = 'com.symfony.www.workflow.transition_blocker.unknown';

    private $message;
    private $code;
    private $parameters;

    /**
     * Creates a new transition blocker.
     *
     * @param string      $message    The blocker message
     * @param string|null $code       The error code of the blocker
     * @param array       $parameters The parameters that may be useful to pass
     *                                around with the blocker
     */
    public function __construct($message, $code = null, array $parameters = array())
    {
        $this->message = $message;
        $this->parameters = $parameters;
        $this->code = $code;
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
    public static function createNotDefined(string $transitionName, string $workflowName)
    {
        return new static(
            sprintf('Transition "%s" is not defined in workflow "%s".', $transitionName, $workflowName),
            self::REASON_CODE_TRANSITION_NOT_DEFINED
        );
    }

    /**
     * Create a blocker, that says the transition cannot be made because the subject
     * is in wrong place (i.e. status).
     *
     * @param string $transitionName
     *
     * @return static
     */
    public static function createNotApplicable(string $transitionName)
    {
        return new static(
            sprintf('Transition "%s" cannot be made, because the subject is not in the required place.', $transitionName),
            self::REASON_CODE_TRANSITION_NOT_APPLICABLE
        );
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
    public static function createUnknownReason(string $transitionName)
    {
        return new static(
            sprintf('Transition "%s" cannot be made, because of unknown reason.', $transitionName),
            self::REASON_CODE_TRANSITION_UNKNOWN
        );
    }

    /**
     * Converts the blocker into a string for debugging purposes.
     *
     * @return string The blocker as string
     */
    public function __toString()
    {
        $code = $this->code;

        if (!empty($code)) {
            $code = ' (code '.$code.')';
        }

        return $this->getMessage().$code;
    }

    /**
     * Returns the blocker message.
     *
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Returns a machine-digestible error code for the blocker.
     *
     * @return string|null The error code
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Returns the parameters that you might want to pass around with the blocker.
     *
     * This is useful if you would like to include the blocker conditions in your
     * error messages.
     *
     * @return array
     */
    public function getParameters()
    {
        return $this->parameters;
    }
}
