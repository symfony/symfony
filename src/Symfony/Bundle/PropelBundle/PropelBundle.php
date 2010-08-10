<?php

namespace Symfony\Bundle\PropelBundle;

use Symfony\Framework\Bundle\Bundle;

class PropelBundle extends Bundle
{
    public function boot()
    {
        require_once $this->container->getParameter('propel.path').'/runtime/lib/Propel.php';

        if (0 === strncasecmp(PHP_SAPI, 'cli', 3)) {
            set_include_path($this->container->getParameter('propel.phing_path').'/classes'.PATH_SEPARATOR.get_include_path());
        }

        $this->container->getPropelService();
    }
}
