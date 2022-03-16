<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\Slugger;

use Symfony\Component\String\Slugger\SluggerInterface;

class SlugConstructArgService
{
    private $slugger;

    public function __construct(SluggerInterface $slugger)
    {
        $this->slugger = $slugger;
    }

    public function hello(): string
    {
        return $this->slugger->slug('Стойността трябва да бъде лъжа');
    }
}
