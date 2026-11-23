<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\Tests\Fixtures;

use Symfony\Component\Validator\Constraints as Assert;

class SequentiallyValidatedItem
{
    #[Assert\Sequentially([new Assert\NotNull(), new Assert\Valid()])]
    public ?Author $author = null;

    #[Assert\Sequentially([new Assert\NotNull(groups: ['strict']), new Assert\Valid()], groups: ['strict'])]
    public ?Author $reviewer = null;
}
