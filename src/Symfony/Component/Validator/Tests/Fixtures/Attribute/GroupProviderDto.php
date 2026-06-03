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
use Symfony\Component\Validator\Tests\Dummy\DummyGroupProvider;

#[Assert\GroupSequenceProvider(provider: DummyGroupProvider::class)]
class GroupProviderDto
{
    public string $firstName = '';
    public string $lastName = '';
}
