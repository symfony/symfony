<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineBundle\Tests\DependencyInjection;

use Doctrine\DBAL\Platforms\AbstractPlatform;

class TestType extends \Doctrine\DBAL\Types\Type
{
    public function getName()
    {
        return 'test';
    }

    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        return '';
    }
}