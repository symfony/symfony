<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Contracts\Tests\Service\Fixtures;

use Symfony\Contracts\Service\Attribute\SubscribedService;
use Symfony\Contracts\Service\ServiceMethodsSubscriberTrait;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

final class WriteOnlyPropertyService implements ServiceSubscriberInterface
{
    use ServiceMethodsSubscriberTrait;

    private mixed $myObject;

    #[SubscribedService]
    public \stdClass $myDependency {
        set => $this->myObject = $value;
    }
}
