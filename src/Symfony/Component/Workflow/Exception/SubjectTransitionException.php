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

use Symfony\Component\Workflow\TransitionBlockerList;

/**
 * Thrown by Workflow when a transition is applied on a subject that is
 * not possible to be made.
 */
class SubjectTransitionException extends LogicException
{
    private $transitionBlockerList;

    public function __construct(string $message, TransitionBlockerList $transitionBlockerList)
    {
        parent::__construct($message);

        $this->transitionBlockerList = $transitionBlockerList;
    }

    public function getTransitionBlockerList(): TransitionBlockerList
    {
        return $this->transitionBlockerList;
    }
}
