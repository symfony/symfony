<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Uid;

/**
 * A v5 UUID contains a SHA1 hash of another UUID and a name.
 *
 * Use Uuid::v5() to compute one.
 *
 * @experimental in 5.1
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class UuidV5 extends Uuid
{
    protected const TYPE = UUID_TYPE_SHA1;
}
