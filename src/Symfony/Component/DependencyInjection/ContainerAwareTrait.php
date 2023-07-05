<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection;

/**
 * ContainerAware trait.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
trait ContainerAwareTrait
{
    /**
     * @var ContainerInterface|null
     */
    protected $container;

    public function setContainer(ContainerInterface $container = null)
    {
        if (1 > \func_num_args()) {
            trigger_deprecation('symfony/dependency-injection', '6.2', 'Calling "%s::%s()" without any arguments is deprecated, pass null explicitly instead.', __CLASS__, __FUNCTION__);
        }

        $this->container = $container;
    }
}
