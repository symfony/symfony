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

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class TaggedDummyHandlerWithUnionTypes
{
    public function __invoke(DummyMessage|SecondMessage $message)
    {
    }

    #[AsMessageHandler]
    public function handleUnionTypeMessage(UnionTypeOneMessage|UnionTypeTwoMessage $message)
    {
    }
}
