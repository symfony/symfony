<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Worker\Consumer;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class ConsumerEvents
{
    const PRE_CONSUME = 'worker.pre_consume';
    const POST_CONSUME = 'worker.post_consume';

    private function __construct()
    {
    }
}
