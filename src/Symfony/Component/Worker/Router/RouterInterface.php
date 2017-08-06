<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Worker\Router;

use Symfony\Component\Worker\MessageCollection;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
interface RouterInterface
{
    public function fetchMessages();

    public function consume(MessageCollection $messageCollection);
}
