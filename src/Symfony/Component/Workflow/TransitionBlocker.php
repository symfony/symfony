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
final class TransitionBlocker
{
    public const BLOCKED_BY_MARKING = '19beefc8-6b1e-4716-9d07-a39bd6d16e34';
    public const BLOCKED_BY_EXPRESSION_GUARD_LISTENER = '326a1e9c-0c12-11e8-ba89-0ed5f89f718b';
    public const UNKNOWN = 'e8b5bbb9-5913-4b98-bfa6-65dbd228a82a';

    private $message;
    private $code;
    private $parameters;

    /**
     * @param string $code       Code is a machine-readable string, usually an UUID
     * @param array  $parameters This is useful if you would like to pass around the condition values, that
     *                           blocked the transition. E.g. for a condition "distance must be larger than
     *                           5 miles", you might want to pass around the value of 5.
     */
    public function __construct(string $message, string $code, array $parameters = [])
    {
        $this->message = $message;
        $this->code = $code;
        $this->parameters = $parameters;
    }

    /**
     * Create a blocker that says the transition cannot be made because it is
     * not enabled.
     *
     * It means the subject is in wrong place (i.e. status):
     * * If the workflow is a state machine: the subject is not in the previous place of the transition.
     * * If the workflow is a workflow: the subject is not in all previous places of the transition.
     */
    public static function createBlockedByMarking(Marking $marking): self
    {
        return new static('The marking does not enable the transition.', self::BLOCKED_BY_MARKING, [
            'marking' => $marking,
        ]);
    }

    /**
     * Creates a blocker that says the transition cannot be made because it has
     * been blocked by the expression guard listener.
     */
    public static function createBlockedByExpressionGuardListener(string $expression): self
    {
        return new static('The expression blocks the transition.', self::BLOCKED_BY_EXPRESSION_GUARD_LISTENER, [
            'expression' => $expression,
        ]);
    }

    /**
     * Creates a blocker that says the transition cannot be made because of an
     * unknown reason.
     */
    public static function createUnknown(string $message = null, int $backtraceFrame = 2): self
    {
        if (null !== $message) {
            return new static($message, self::UNKNOWN);
        }

        $caller = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, $backtraceFrame + 1)[$backtraceFrame]['class'] ?? null;

        if (null !== $caller) {
            return new static("The transition has been blocked by a guard ($caller).", self::UNKNOWN);
        }

        return new static('The transition has been blocked by a guard.', self::UNKNOWN);
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
