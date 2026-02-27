<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Console;

use Symfony\Component\Console\ArgumentResolver\ValueResolver\ValueResolverInterface;
use Symfony\Component\Console\Attribute\Reflection\ReflectionMember;
use Symfony\Component\Console\Input\InputInterface;

class CustomTypeValueResolver implements ValueResolverInterface
{
    public function resolve(string $argumentName, InputInterface $input, ReflectionMember $member): iterable
    {
        $type = $member->getType();

        if (!$type instanceof \ReflectionNamedType || CustomType::class !== $type->getName()) {
            return [];
        }

        $name = $input->hasArgument('name') ? $input->getArgument('name') : 'default';

        yield new CustomType('resolved:' . $name);
    }
}
