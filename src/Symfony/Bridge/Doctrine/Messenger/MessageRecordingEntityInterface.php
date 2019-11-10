<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Messenger;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 * @author Matthias Noback <matthiasnoback@gmail.com>
 * @author Valentin Udaltsov <udaltsov.valentin@gmail.com>
 */
interface MessageRecordingEntityInterface
{
    /**
     * @param callable $dispatcher callable(object[] $messages): void
     */
    public function dispatchMessages(callable $dispatcher): void;
}
