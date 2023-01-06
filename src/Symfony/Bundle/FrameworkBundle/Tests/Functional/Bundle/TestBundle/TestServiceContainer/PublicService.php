<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\TestServiceContainer;

class PublicService
{
    private $nonPublicService;

    private $privateService;

    public function __construct(NonPublicService $nonPublicService, PrivateService $privateService)
    {
        $this->nonPublicService = $nonPublicService;
        $this->privateService = $privateService;
    }
}
