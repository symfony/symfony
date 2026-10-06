<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\Tests\Fixtures;

class ScalarProperties
{
    public int $id;
    public ?string $nickname;
    public bool $active = true;
    public float $price;
    public string $title = 'untitled';
    public true $enabled = true;
}
