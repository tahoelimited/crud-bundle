<?php


namespace Tahoe\Bundle\CrudBundle\Handler;

use Doctrine\ORM\EntityManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Tahoe\Bundle\CrudBundle\EventListener\CrudEvent;

class EntityHandler implements HandlerInterface
{
    /**
     * @var EntityManager
     */
    protected $entityManager;
    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;
    /**
     * @var ParameterBag
     */
    protected $parameters;

    /** @var  Request */
    protected $request;

    public function __construct($entityManager, $eventDispatcher, $request = null)
    {
        $this->entityManager = $entityManager;
        $this->eventDispatcher = $eventDispatcher;
        $this->request = $request;
    }

    /**
     * @return mixed
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * @param mixed $parameters
     *
     * @return $this
     */
    public function setParameters($parameters)
    {
        $this->parameters = $parameters;

        return $this;
    }

    /**
     * @param object  $entity
     * @param boolean $withFlush
     */
    public function create($entity, $withFlush = false)
    {
        $this->dispatchEvent('pre_create', $entity);

        $this->entityManager->persist($entity);

        $this->dispatchEvent('post_create', $entity);

        if ($withFlush) {
            $this->entityManager->flush();
        }
    }

    /**
     * @param object  $entity
     * @param boolean $withFlush
     */
    public function update($entity, $withFlush = false)
    {
        $this->dispatchEvent('pre_update', $entity);

        $this->entityManager->persist($entity);

        $this->dispatchEvent('post_update', $entity);

        if ($withFlush) {
            $this->entityManager->flush();
        }
    }

    public function delete($entity, $withFlush = false)
    {
        $this->dispatchEvent('pre_delete', $entity);

        $this->entityManager->remove($entity);

        $this->dispatchEvent('post_delete', $entity);

        if ($withFlush) {
            $this->entityManager->flush();
        }
    }

    public function dispatchEvent($name, $resource)
    {
        $event_name = sprintf('tahoe_crud.on_%s_%s', strtolower($this->getParameter('entityName')), $name);
        $this->eventDispatcher->dispatch($event_name, new CrudEvent($this->request, $resource));
    }

    public function getParameter($name, $default = null)
    {
        return $this->parameters->has($name) ? $this->parameters->get($name) : $default;
    }

}
