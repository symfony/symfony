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
    private NonPublicService $nonPublicService;
    private PrivateService $privateService;
    private PrivateService $decorated;
    public object $nonSharedService;
    public object $nonSharedAlias;

    public function __construct(
        NonPublicService $nonPublicService,
        PrivateService $privateService,
        PrivateService $decorated,
        object $nonSharedService,
        object $nonSharedAlias,
    ) {
        $this->nonPublicService = $nonPublicService;
        $this->privateService = $privateService;
        $this->decorated = $decorated;
        $this->nonSharedService = $nonSharedService;
        $this->nonSharedAlias = $nonSharedAlias;
    }
}
