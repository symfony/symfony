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
 * Yields completed responses, returned by ApiClientInterface::complete().
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
interface ResponseIteratorInterface extends \Iterator
{
    public function current(): ResponseInterface;
}
