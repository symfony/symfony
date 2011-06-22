<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabio B. Silva <fabio.bat.silva@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Annotation;

use Symfony\Component\DependencyInjection\Annotation;

/**
 * Annotation class for @Inject().
 *
 * @author Fabio B. Silva <fabio.bat.silva@gmail.com>
 */
class Inject extends Annotation
{
    private $source;
  

    /**
     * Constructor.
     *
     * @param array $data An array of key/value parameters or service identifier
     */
    public function __construct(array $data)
    {
        if (isset($data['value']))
        {
            $data['source'] = $data['value'];
            unset($data['value']);
        }

        $this->setOptions($data);
    }

    /**
     * @return string 
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * @param string $source 
     */
    protected function setSource($source)
    {
        $this->source = $source;
    }

}