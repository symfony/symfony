<?php

namespace Symfony\Component\Security\Core\Util;

interface SeedProviderInterface
{
    function loadSeed();
    function updateSeed($seed);
}