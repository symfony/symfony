<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Contracts\HttpClient;

/**
 * Given the parameters of the request, give a unique name to identify a response.
 *
 * @author Gary PEGEOT <garypegeot@gmail.com>
 */
interface ResponseNamingStrategyInterface
{
    public function name(string $method, string $url, array $options): string;
}
