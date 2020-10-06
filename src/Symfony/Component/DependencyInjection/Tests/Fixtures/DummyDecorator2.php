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

use Symfony\Component\DependencyInjection\DecoratorInterface;

class DummyDecorator2 implements DecoratorInterface, DummyInterface
{
    /**
     * @var DummyInterface
     */
    protected $decorated;

    public function __construct(DummyInterface $decorated)
    {
        $this->decorated = $decorated;
    }

    public static function getDecoratedServiceId(): string
    {
        return 'dummy';
    }

    public function sayHello(): string
    {
        return sprintf('%s & Decorator2', $this->decorated->sayHello());
    }
}
