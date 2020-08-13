<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DataCollector;

/**
 * @author Laurent VOULLEMIER <laurent.voullemier@gmail.com>
 */
abstract class AbstractDataCollector implements TemplateAwareDataCollectorInterface
{
    /**
     * @var array
     */
    protected $data = [];

    public function getName(): string
    {
        return static::class;
    }

    public function reset(): void
    {
        $this->data = [];
    }

    public static function getTemplate(): ?string
    {
        return null;
    }
}
