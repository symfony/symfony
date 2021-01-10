<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Symfony\Contracts\Service\Attribute\Tag;

#[Tag(name: 'app.custom_tag', attributes: ['foo' => 'bar'])]
#[Tag(name: 'app.custom_tag', attributes: ['bar' => 'baz'])]
final class TaggedService1
{
}
