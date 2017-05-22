<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Worker\Loop;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
interface ConfigurableLoopInterface extends LoopInterface
{
    public function setName($name);
}
