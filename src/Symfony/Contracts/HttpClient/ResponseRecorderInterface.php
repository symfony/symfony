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
 * In charge of saving (file system, database, ...) and retrieving a `ResponseInterface` object.
 *
 * @author Gary PEGEOT <garypegeot@gmail.com>
 */
interface ResponseRecorderInterface
{
    public function record(string $name, ResponseInterface $response): void;

    public function replay(string $name): ?ResponseInterface;
}
