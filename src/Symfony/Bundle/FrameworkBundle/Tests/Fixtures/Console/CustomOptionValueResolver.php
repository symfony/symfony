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

class CustomOptionValueResolver implements ValueResolverInterface
{
    public function resolve(string $argumentName, InputInterface $input, ReflectionMember $member): iterable
    {
        $type = $member->getType();

        if ('customOption' !== $argumentName || !$type instanceof \ReflectionNamedType || CustomType::class !== $type->getName()) {
            return [];
        }

        $value = $input->hasOption('format') ? $input->getOption('format') : 'default';

        yield new CustomType('option:' . $value);
    }
}
