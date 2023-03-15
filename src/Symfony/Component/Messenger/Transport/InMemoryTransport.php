<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport;

use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport as BaseInMemoryTransport;

trigger_deprecation('symfony/messenger', '6.3', 'The "%s" class is deprecated, use "%s" instead. ', InMemoryTransport::class, BaseInMemoryTransport::class);

/**
 * @deprecated since Symfony 6.3, use {@link BaseInMemoryTransport} instead
 */
class InMemoryTransport extends BaseInMemoryTransport
{
}
