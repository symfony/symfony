<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\EventDispatcher;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * A helper class to provide BC/FC with the legacy signature of EventDispatcherInterface::dispatch().
 *
 * This class should be deprecated in Symfony 5.1
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class LegacyEventDispatcherProxy
{
    public static function decorate(?EventDispatcherInterface $dispatcher): ?EventDispatcherInterface
    {
        return $dispatcher;
    }
}
