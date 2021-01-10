<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Contracts\Service\Attribute;

/**
 * A service tag.
 *
 * This attribute holds meta information on the annotated class that can be processed by a service container.
 *
 * @author Alexander M. Turek <me@derrabus.de>
 */
interface TagInterface
{
    /**
     * The name of the tag.
     *
     * If the service container implementation offers a way to query services or service definitions by tag,
     * this name shall be used as search input.
     */
    public function getName(): string;

    /**
     * Additional attributes of this tag.
     *
     * @return mixed[]
     */
    public function getAttributes(): array;
}
