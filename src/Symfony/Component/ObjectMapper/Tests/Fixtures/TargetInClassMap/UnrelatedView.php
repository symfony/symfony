<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\TargetInClassMap;

/**
 * Only exists so Source carries a class-level #[Map] and metadata is read from the source side.
 */
class UnrelatedView
{
    public int $id = 0;
}
