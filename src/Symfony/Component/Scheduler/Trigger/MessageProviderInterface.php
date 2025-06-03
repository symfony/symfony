<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Trigger;

use Symfony\Component\Scheduler\Generator\MessageContext;

interface MessageProviderInterface
{
    /**
     * @return iterable<object>
     */
    public function getMessages(MessageContext $context): iterable;

    public function getId(): string;
}
