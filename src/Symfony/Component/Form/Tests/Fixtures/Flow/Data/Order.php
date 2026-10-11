<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Fixtures\Flow\Data;

final class Order
{
    public string $currentStep = '';
    public Contact $buyer;
    public Contact $recipient;

    public function __construct()
    {
        $this->buyer = new Contact();
        $this->recipient = new Contact();
    }
}
