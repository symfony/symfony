<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\MarkingStore;

/**
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
abstract class AbstractMarkingStore implements MarkingStoreInterface
{
    public function __construct(protected string $property)
    {
    }
}
