<?php


namespace Symfony\Component\Profiler\ProfileData;


use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class RouterData
{
    protected $redirect = false;
    protected $url;
    protected $route;

    public function __construct(Response $response, $route = null)
    {
        if ( $response instanceof RedirectResponse ) {
            $this->redirect = true;
            $this->url = $response->getTargetUrl();
        }
        $this->route = $route;
    }

    /**
     * @return bool Whether this request will result in a redirect
     */
    public function getRedirect()
    {
        return $this->redirect;
    }

    /**
     * @return string|null The target URL
     */
    public function getTargetUrl()
    {
        return $this->url;
    }

    /**
     * @return string|null The target route
     */
    public function getTargetRoute()
    {
        return $this->route;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'router';
    }
}