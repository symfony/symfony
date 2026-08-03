<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Fixtures\Attribute;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\GroupSequenceProviderInterface;

#[Assert\GroupSequenceProvider(cascadeCurrentGroup: true)]
class GroupSequenceProviderCascadeEntity implements GroupSequenceProviderInterface
{
    #[Assert\Valid]
    public ?GroupSequenceProviderCascadeChild $child = null;

    public function getGroupSequence(): array|GroupSequence
    {
        // a plain array, as in the reported case
        return ['GroupSequenceProviderCascadeEntity', 'my_group'];
    }
}

class GroupSequenceProviderCascadeChild
{
    #[Assert\Length(exactly: 2, groups: ['my_group'])]
    public ?string $a = null;
}
