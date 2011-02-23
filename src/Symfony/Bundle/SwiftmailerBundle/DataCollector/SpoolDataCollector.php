<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SwiftmailerBundle\DataCollector;

use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * DoctrineDataCollector.
 *
 * @author Clément JOBEILI <clement.jobeili@gmail.com>
 */
class SpoolDataCollector extends DataCollector
{
    protected $path;
    
    public function __construct($path)
    {
        $this->path = $path;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        $messageCount = 0; 
        
        foreach (new \DirectoryIterator($this->path) as $file) {
            $file = $file->getRealPath();
            
            if (strpos($file, '.message')) {
                $messageCount++;
            }
        }
        
        $this->data['messageCount'] = $messageCount;
    }
    
    public function getMessageCount()
    {
        return $this->data['messageCount'];
    }
    
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'spool';
    }
}
