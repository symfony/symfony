<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Stamp;

/**
 * A stamp that should not be included with the Envelope if sent to a transport.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
interface NonSendableStampInterface extends StampInterface
{
}
