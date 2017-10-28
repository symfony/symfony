<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authentication\Token;

/**
 * DummyToken allows fixing #7104 without introducing any BC break.
 *
 * @author Mathieu Lechat <math.lechat@gmail.com>
 *
 * @internal
 */
class DummyToken extends AbstractToken
{
    public function __construct()
    {
        parent::__construct(array());

        $this->setUser('dummy');
    }

    /**
     * {@inheritdoc}
     */
    public function getCredentials()
    {
        return null;
    }
}
