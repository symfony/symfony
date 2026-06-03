<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Message;

use Symfony\Component\Messenger\Stamp\StampInterface;

interface DefaultStampsProviderInterface
{
    /**
     * List of stamps which will be automatically added to the envelope,
     * if there is no other stamp of the same class already set.
     *
     * @return array<StampInterface>
     */
    public function getDefaultStamps(): array;
}
