<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Command;

use Symfony\Component\Cache\Command\CachePoolClearCommand as BaseCachePoolClearCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\HttpKernel\CacheClearer\Psr6CacheClearer;

trigger_deprecation('symfony/framework-bundle', '8.2', 'The "%s" class is deprecated, use "%s" instead.', CachePoolClearCommand::class, BaseCachePoolClearCommand::class);

/**
 * @deprecated since Symfony 8.2, use Symfony\Component\Cache\Command\CachePoolClearCommand instead
 */
#[AsCommand(name: 'cache:pool:clear', description: 'Clear cache pools')]
class CachePoolClearCommand extends BaseCachePoolClearCommand
{
    /**
     * @param string[]|null $poolNames
     */
    public function __construct(Psr6CacheClearer $poolClearer, ?array $poolNames = null)
    {
        // the application is not known yet, so the container can only be handed over lazily
        parent::__construct(fn () => $this->getApplication()->getKernel()->getContainer(), $poolClearer, $poolNames);
    }
}
