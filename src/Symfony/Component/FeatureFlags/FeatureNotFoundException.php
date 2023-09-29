<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\FeatureFlags;

use Psr\Container\NotFoundExceptionInterface;

class FeatureNotFoundException extends \Exception implements NotFoundExceptionInterface
{
    public function __construct(string $id)
    {
        parent::__construct("Feature \"$id\" not found.");
    }
}
