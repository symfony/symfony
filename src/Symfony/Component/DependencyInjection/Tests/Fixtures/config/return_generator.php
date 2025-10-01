<?php

use Symfony\Component\DependencyInjection\Tests\Fixtures\AcmeConfig;

return function () {
    yield new AcmeConfig(['color' => 'red']);
};
