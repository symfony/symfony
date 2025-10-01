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

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
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
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testCreateDoctrineStyle()
    {
        $this->expectUserDeprecationMessage('Since symfony/validator 7.4: Support for passing an array of options to "Symfony\Component\Validator\Constraints\GroupSequence::__construct()" is deprecated.');

        $sequence = new GroupSequence(['value' => ['Group 1', 'Group 2']]);

        $this->assertSame(['Group 1', 'Group 2'], $sequence->groups);
    }
}
