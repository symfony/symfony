<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Dummy;

use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\GroupProviderInterface;

class DummyGroupProvider implements GroupProviderInterface
{
    public function getGroups(object $object): array|GroupSequence
    {
        return ['foo', 'bar'];
    }
}
