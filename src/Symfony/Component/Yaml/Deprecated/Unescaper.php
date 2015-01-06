<?php

namespace Symfony\Component\Yaml\Deprecated;

trigger_error('Constant ENCODING in class Symfony\Component\Yaml\Unescaper is deprecated since version 2.5 and will be removed in 3.0.', E_USER_DEPRECATED);

/**
 * @deprecated since version 2.7, to be removed in 3.0.
 * @internal
 */
final class Unescaper
{
    const ENCODING = 'UTF-8';

    private function __construct()
    {
        
    }
}
