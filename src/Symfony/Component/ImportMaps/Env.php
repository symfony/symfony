<?php

declare(strict_types=1);

namespace Symfony\Component\ImportMaps;

/**
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
enum Env: string
{
    case Production = 'production';
    case Development = 'development';
}
