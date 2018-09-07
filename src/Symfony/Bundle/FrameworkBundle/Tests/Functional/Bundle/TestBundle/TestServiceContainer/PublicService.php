<?php

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
