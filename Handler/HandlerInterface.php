<?php


namespace Tahoe\Bundle\CrudBundle\Handler;


interface HandlerInterface
{
    public function __construct($entityManager, $eventDispatcher);

    public function create($entity);

    public function update($entity);

    public function delete($entity);
}