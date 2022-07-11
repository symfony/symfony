<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\EventDispatcher\Debug;

/**
 * The NamedListener marks listener that are able to provide naming for themselves.
 *
 * @author Quentin Devos <quentin@devos.pm>
 */
interface NamedListener
{
    public function getName(): string;

    public function getPretty(): string;

    public function getCallableRef(): ?string;
}
