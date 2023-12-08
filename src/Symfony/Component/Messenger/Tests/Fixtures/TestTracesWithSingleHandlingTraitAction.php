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

use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\SingleHandlingTrait;

/**
 * @see \Symfony\Component\Messenger\Tests\TraceableMessageBusTest::testItTracesDispatchWhenSingleHandlingTraitIsUsed
 */
class TestTracesWithSingleHandlingTraitAction
{
    use SingleHandlingTrait;

    public function __construct(MessageBusInterface $messageBus)
    {
        $this->messageBus = $messageBus;
    }

    public function __invoke($message)
    {
        $this->handle($message);
    }
}
