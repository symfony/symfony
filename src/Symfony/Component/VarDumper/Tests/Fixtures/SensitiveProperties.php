<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Tests\Fixtures;

use Symfony\Component\VarDumper\Attribute\SensitiveElement;

#[SensitiveElement(property: 'password')]
class SensitiveProperties
{
    private string $username = 'root';

    private string $password = 'toor';

    protected SensitiveFoo $sensitiveFoo;

    public SensitiveBarProperties $sensitiveBarProperties;

    public function __construct()
    {
        $this->sensitiveFoo = new SensitiveFoo();
        $this->sensitiveBarProperties = new SensitiveBarProperties();
    }
}

#[SensitiveElement]
class SensitiveFoo
{
}

#[SensitiveElement(['sensitiveInfo'])]
class SensitiveBarProperties
{
    private string $sensitiveInfo = 'password';

    private int $publicInfo = 123;
}

#[SensitiveElement(property: ['foo', 'bar', 'qux'])]
class SensitiveClassWithAllVisibilities
{
    public $foo;

    protected $bar;

    private $qux;
}
