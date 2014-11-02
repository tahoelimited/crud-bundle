<?php

namespace Tahoe\Bundle\CrudBundle\EventListener;


use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\HttpFoundation\Request;

class CrudEvent extends GenericEvent
{
    /**
     * @var Request
     */
    private $request;
    private $resource;

    public function __construct(Request $request, $resource)
    {
        parent::__construct($resource);
        $this->request = $request;
    }

    /**
     * @return Request
     */
    public function getRequest()
    {
        return $this->request;
    }
}
