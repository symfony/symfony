<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Builder\CodeGenerator\Fixtures;

use Test\CodeGenerator\Fixtures\Bar;
use Test\CodeGenerator\Fixtures\Cat;
use Test\CodeGenerator\Fixtures\Foo;
use Test\CodeGenerator\Fixtures\MyAttribute;

/**
 * Perfect class comment.
 *
 * It has some lines
 */
#[MyAttribute(name: 'test')]
class Full extends Cat implements Foo, Bar
{
    private string $name;

    public function __construct(string $name = 'foobar')
    {
        $this->name = $name;
    }

    /**
     * Returns the name of the cat.
     */
    public function getName(): string
    {
        return $this->name;
    }
}
