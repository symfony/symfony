<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Util;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Util\OptionsResolverWrapper;

class OptionsResolverWrapperTest extends TestCase
{
    public function testUndefinedOptionsAreRecorded()
    {
        $resolver = new OptionsResolverWrapper();
        $resolver
            ->setNormalizer('set_normalizer', static fn () => null)
            ->addNormalizer('add_normalizer', static fn () => null)
            ->setAllowedValues('set_allowed_values', 'bar')
            ->addAllowedValues('add_allowed_values', 'bar')
            ->setAllowedTypes('set_allowed_types', 'string')
            ->addAllowedTypes('add_allowed_types', 'string')
            ->setDeprecated('set_deprecated', 'symfony/form', '6.4')
            ->setInfo('set_info', 'bar');

        $this->assertSame([
            'set_normalizer',
            'add_normalizer',
            'set_allowed_values',
            'add_allowed_values',
            'set_allowed_types',
            'add_allowed_types',
            'set_deprecated',
            'set_info',
        ], $resolver->getUndefinedOptions());
        $this->assertSame([], $resolver->getDefinedOptions());
    }

    public function testGetInfoOfUndefinedOption()
    {
        $resolver = new OptionsResolverWrapper();

        $this->assertNull($resolver->getInfo('foo'));
        $this->assertSame([], $resolver->getUndefinedOptions());
    }
}
