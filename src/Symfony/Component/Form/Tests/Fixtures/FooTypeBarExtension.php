<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Fixtures;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;

class FooTypeBarExtension extends AbstractTypeExtension
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->setAttribute('bar', 'x');
    }

    public function getAllowedOptionValues(): array
    {
        return [
            'a_or_b' => ['c'],
        ];
    }

    public static function getExtendedTypes(): iterable
    {
        return [FooType::class];
    }
}
