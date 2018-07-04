<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 * @author Matthias Noback <matthiasnoback@gmail.com>
 */
interface RecordedMessageCollectionInterface
{
    /**
     * Fetch recorded messages.
     *
     * @return object[]
     */
    public function getRecordedMessages(): array;

    /**
     * Remove all recorded messages.
     */
    public function resetRecordedMessages(): void;
}
