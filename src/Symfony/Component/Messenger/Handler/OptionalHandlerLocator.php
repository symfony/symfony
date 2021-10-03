<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\Messenger\Handler;

use Symfony\Component\Messenger\Envelope;

final class OptionalHandlerLocator extends HandlersLocator
{
    /**
     * {@inheritdoc}
     */
    public function getHandlers(Envelope $envelope): iterable
    {
        yield from $this->doGetHandlers($envelope);
    }
}
