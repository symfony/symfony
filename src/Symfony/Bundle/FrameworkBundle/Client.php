<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Client as BaseClient;
use Symfony\Component\HttpKernel\Profiler\Profile as HttpProfile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Client simulates a browser and makes requests to a Kernel object.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Client extends BaseClient
{
    private $hasPerformedRequest = false;

    /**
     * Returns the container.
     *
     * @return ContainerInterface
     */
    public function getContainer()
    {
        return $this->kernel->getContainer();
    }

    /**
     * Returns the kernel.
     *
     * @return HttpKernelInterface
     */
    public function getKernel()
    {
        return $this->kernel;
    }

    /**
     * Gets the profile associated with the current Response.
     *
     * @return HttpProfile A Profile instance
     */
    public function getProfile()
    {
        if (!$this->kernel->getContainer()->has('profiler')) {
            return false;
        }

        return $this->kernel->getContainer()->get('profiler')->loadProfileFromResponse($this->response);
    }

    /**
     * Makes a request.
     *
     * @param Request $request A Request instance
     *
     * @return Response A Response instance
     */
    protected function doRequest($request)
    {
        // avoid shutting down the Kernel if no request has been performed yet
        // WebTestCase::createClient() boots the Kernel but do not handle a request
        if ($this->hasPerformedRequest) {
            $this->kernel->shutdown();
        } else {
            $this->hasPerformedRequest = true;
        }

        return parent::doRequest($request);
    }

    /**
     * Returns the script to execute when the request must be insulated.
     *
     * It assumes that the autoloader is named 'autoload.php' and that it is
     * stored in the same directory as the kernel (this is the case for the
     * Symfony Standard Edition). If this is not your case, create your own
     * client and override this method.
     *
     * @param Request $request A Request instance
     *
     * @return string The script content
     */
    protected function getScript($request)
    {
        $kernel = str_replace("'", "\\'", serialize($this->kernel));
        $request = str_replace("'", "\\'", serialize($request));

        $r = new \ReflectionObject($this->kernel);

        $autoloader = dirname($r->getFileName()).'/autoload.php';
        if (is_file($autoloader)) {
            $autoloader = str_replace("'", "\\'", $autoloader);
        } else {
            $autoloader = '';
        }

        $path = str_replace("'", "\\'", $r->getFileName());

        return <<<EOF
<?php

if ('$autoloader') {
    require_once '$autoloader';
}
require_once '$path';

\$kernel = unserialize('$kernel');
\$kernel->boot();
echo serialize(\$kernel->handle(unserialize('$request')));
EOF;
    }
}
