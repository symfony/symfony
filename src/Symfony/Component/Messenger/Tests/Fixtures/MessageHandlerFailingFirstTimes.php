<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Fixtures;

class MessageHandlerFailingFirstTimes
{
    private $remainingFailures;

    private $called = 0;

    public function __construct(int $throwExceptionOnFirstTries = 0)
    {
        $this->remainingFailures = $throwExceptionOnFirstTries;
    }

    public function __invoke(DummyMessage $message)
    {
        if ($this->remainingFailures > 0) {
            --$this->remainingFailures;
            throw new \Exception('Handler should throw Exception.');
        }

        ++$this->called;
    }

    public function getTimesCalledWithoutThrowing(): int
    {
        return $this->called;
    }
}
