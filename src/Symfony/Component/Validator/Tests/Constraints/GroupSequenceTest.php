<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\GroupSequence;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class GroupSequenceTest extends TestCase
{
    public function testCreate()
    {
        $sequence = new GroupSequence(['Group 1', 'Group 2']);

        $this->assertSame(['Group 1', 'Group 2'], $sequence->groups);
        $this->assertFalse($sequence->cascadeCurrentGroup);
    }

    public function testUnserializeSequenceSerializedBeforeCascadeCurrentGroupExisted()
    {
        // a validator.mapping.cache entry written before the flag existed carries no "cascadeCurrentGroup"
        $serialized = 'O:'.\strlen(GroupSequence::class).':"'.GroupSequence::class.'":1:{s:6:"groups";a:1:{i:0;s:7:"Group 1";}}';

        $sequence = unserialize($serialized);

        $this->assertSame(['Group 1'], $sequence->groups);
        $this->assertFalse($sequence->cascadeCurrentGroup);
    }
}
