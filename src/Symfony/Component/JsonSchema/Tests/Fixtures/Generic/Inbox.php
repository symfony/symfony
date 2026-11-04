<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\Tests\Fixtures\Generic;

class Inbox
{
    /** @var Envelope<string> */
    public Envelope $message;

    /** @var Envelope<Customer> */
    public Envelope $customer;
}
