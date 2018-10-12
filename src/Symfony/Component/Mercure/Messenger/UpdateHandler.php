<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\Mercure\Messenger;
use Symfony\Component\Mercure\Publisher;
use Symfony\Component\Mercure\Update;

/**
 * Publishes an update.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
final class UpdateHandler
{
    private $publisher;

    public function __construct(Publisher $publisher)
    {
        $this->publisher = $publisher;
    }

    public function __invoke(Update $update)
    {
        ($this->publisher)($update);
    }
}
