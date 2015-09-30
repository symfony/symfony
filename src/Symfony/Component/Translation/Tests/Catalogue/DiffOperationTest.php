<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Catalogue;

use Symfony\Component\Translation\Catalogue\DiffOperation;
use Symfony\Component\Translation\MessageCatalogueInterface;

/**
 * @group legacy
 */
class DiffOperationTest extends TargetOperationTest
{
    protected function createOperation(MessageCatalogueInterface $source, MessageCatalogueInterface $target)
    {
        return new DiffOperation($source, $target);
    }
}
