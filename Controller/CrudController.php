<?php

namespace Tahoe\Bundle\CrudBundle\Controller;

use Doctrine\Bundle\DoctrineBundle\Mapping\DisconnectedMetadataFactory;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tahoe\Bundle\CrudBundle\Factory\FactoryInterface;
use Tahoe\Bundle\CrudBundle\Handler\EntityHandler;
use Tahoe\Bundle\CrudBundle\Handler\HandlerInterface;
use Tahoe\Bundle\CrudBundle\Repository\RepositoryInterface;
use Tahoe\Bundle\CrudBundle\EventListener\CrudEvent;


/**
 * Class CrudController
 *
 * @author Konrad Podgórski <konrad.podgorski@gmail.com>
 */
class CrudController extends Controller
{
    /**
     * @var RepositoryInterface
     */
    protected $repository;
    /**
     * @var FactoryInterface
     */
    protected $factory;
    /**
     * @var HandlerInterface|null
     */
    protected $handler;
    /**
     * @var ContainerInterface
     */
    protected $container;
    /**
     * @var ParameterBag
     */
    protected $parameters;

    public function setContainer(ContainerInterface $container = null)
    {
        parent::setContainer($container);

        $this->handler = new EntityHandler(
            $this->container->get('doctrine.orm.entity_manager'),
            $this->container->get('event_dispatcher'),
            $this->get('request')
        );

        $this->handler->setParameters($this->parameters);
    }

    public function __construct()
    {
        $this->parameters = new ParameterBag();
    }

    public function getParameter($name, $default = null)
    {
        return $this->parameters->has($name) ? $this->parameters->get($name) : $default;
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction()
    {
        $entities = $this->repository->findAll();

        return $this->render(
            $this->getTemplateName('index'),
            array(
                'entities' => $entities,
                'routePrefix' => $this->getResourcePathPrefix(),
                'entityName' => $this->getParameter('entityName')
            )
        );
    }

    protected function getTemplateName($type)
    {
        $specificTemplate = sprintf(
            '%s:Crud/%s:%s.html.twig',
            $this->getParameter('bundleName'),
            $this->getParameter('entityName'),
            $type
        );

        if ($this->container->get('templating')->exists($specificTemplate)) {
            return $specificTemplate;
        }

        $bundleTemplate = sprintf(
            '%s:Crud:%s.html.twig',
            $this->getParameter('bundleName'),
            $type
        );

        if ($this->container->get('templating')->exists($bundleTemplate)) {
            return $bundleTemplate;
        }

        return sprintf(
            'TahoeCrudBundle:Crud:%s.html.twig',
            $type
        );
    }

    private function getResourcePrefix()
    {
        return str_replace(' ', '_', strtolower($this->getParameter('entityName')));
    }

    public function getResourcePathPrefix()
    {
        return $this->getParameter(
            'resourcePathPrefix',
            str_replace(" ", "_", strtolower($this->getParameter('entityName')))
        );
    }

    /**
     * @return array
     */
    public function getParameters()
    {
        return $this->parameters->all();
    }

    /**
     * @param array $parameters
     *
     * @return $this
     */
    public function setParameters($parameters)
    {
        $this->parameters->clear();
        $this->parameters->add($parameters);

        return $this;
    }

    public function newAction(Request $request)
    {
        $entity = $this->factory->createNew();
        $form = $this->createCreateForm($entity);
        $form->handleRequest($request);

        if ($form->isValid()) {
            $this->handler->create($entity, true);
            $this->setFlash('success', 'create');

            return $this->redirectToResource($entity);
        }

        return $this->render(
            $this->getTemplateName('new'),
            array(
                'form' => $form->createView(),
                'routePrefix' => $this->getResourcePathPrefix(),
                'entityName' => $this->getParameter('entityName'),
                'additional_params' => $this->getAdditionalParams()
            )
        );
    }

    protected function createCreateForm($entity)
    {
        return $this->createForm(
            sprintf('%s_form', str_replace(" ", "", strtolower($this->getParameter('entityName')))),
            $entity,
            array(
                'method' => 'post',
                'action' => $this->generateUrl(sprintf('%s_create', $this->getResourcePathPrefix()), $this->getAdditionalParams())
            )
        );
    }

    /**
     * @return array
     */
    protected function getAdditionalParams()
    {
        return array();
    }

    public function dispatchEvent($name, $resource)
    {
        $event_name = sprintf('tahoe_xfrify.crud.on_%s_%s', strtolower($this->entityName), $name);
        $this->get('event_dispatcher')->dispatch($event_name, new GenericEvent($resource));
    }

    public function setFlash($type, $eventName, $translate = true, $params = array())
    {
        /** @var FlashBag $flashBag */
        $flashBag = $this->container->get('session')->getBag('flashes');
        if (!$translate) {
            $flashBag->add($type, $eventName);
        } else {
            $flashBag->add($type, $this->translateFlashMessage($eventName, $params));
        }

    }

    /**
     * @param string $event
     * @param array  $params
     *
     * @return string
     */
    private function translateFlashMessage($event, $params = array())
    {
        $message = sprintf('tahoe_crud.entity.%s', $event);

        return $this->container->get('translator')->trans(
            $message,
            array_merge(array('%entityName%' => $this->getParameter('entityName')), $params),
            'flashes'
        );
    }

    public function redirectToResource($resource)
    {
        return $this->redirect(
            $this->generateUrl(
                $this->getResourcePathPrefix() . "_show",
                array_merge($this->getAdditionalParams(), array('id' => $resource->getId()))
            )
        );
    }

    public function showAction(Request $request)
    {
        $entity = $this->findOr404($request);

        return $this->render(
            $this->getTemplateName('show'),
            array(
                'entity' => $entity,
                'routePrefix' => $this->getResourcePathPrefix(),
                'entityName' => $this->getParameter('entityName'),
                'properties' => $this->getEntityFields($entity),
                'additional_params' => $this->getAdditionalParams()
            )
        );
    }

    /**
     * @param Request $request
     * @param string  $identifier
     *
     * @return object
     *
     * @throws NotFoundHttpException
     */
    public function findOr404(Request $request, $identifier = 'id')
    {

        $entity = $this->repository->find($request->get($identifier));

        if (!$entity) {
            throw $this->createNotFoundException(
                sprintf('Unable to find %s entity.', $this->getParameter('entityName'))
            );
        }

        if (!$entity) {
            throw new NotFoundHttpException(
                sprintf(
                    'Requested %s does not exist with criteria specified',
                    $this->getParameter('entityName')
                )
            );
        }

        return $entity;
    }

    protected function getEntityFields($entity)
    {
        $publicMethods = get_class_methods($entity);
        $fields = [];
        foreach($publicMethods as $method) {
            if (false !== strpos($method, "get")) {
                $method = str_replace("get", "", $method);
                $fields[$this->from_camel_case($method)] = lcfirst($method);
            }
        }
        return $fields;
    }

    private function from_camel_case($input) {
        preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $input, $matches);
        $ret = $matches[0];
        foreach ($ret as &$match) {
            $match = $match == strtoupper($match) ? strtolower($match) : lcfirst($match);
        }
        return implode('_', $ret);
    }



    public function editAction(Request $request)
    {
        $entity = $this->findOr404($request);

        $form = $this->createEditForm($entity);
        $form->handleRequest($request);

        if ($form->isValid()) {

            $this->handler->update($entity, true);

            $this->setFlash('success', 'update');

            return $this->redirectToResource($entity);
        }

        return $this->render(
            $this->getTemplateName('edit'),
            array(
                'form' => $form->createView(),
                'routePrefix' => $this->getResourcePathPrefix(),
                'entityName' => $this->getParameter('entityName'),
                'additional_params' => $this->getAdditionalParams()
            )
        );
    }

    protected function createEditForm($entity)
    {
        return $this->createForm(
            sprintf('%s_form', str_replace(" ", "", strtolower($this->getParameter('entityName')))),
            $entity,
            array(
                'method' => 'post',
                'action' => $this->generateUrl(
                    sprintf('%s_update', $this->getResourcePathPrefix()),
                    array_merge(array('id' => $entity->getId()), $this->getAdditionalParams())
                )
            )
        );
    }

    /**
     * @param FactoryInterface $factory
     *
     * @return $this
     */
    public function setFactory($factory)
    {
        $this->factory = $factory;

        return $this;
    }

    /**
     * @param \Doctrine\ORM\EntityRepository $repository
     *
     * @return $this
     */
    public function setRepository($repository)
    {
        $this->repository = $repository;

        return $this;
    }

    /**
     * @return null|HandlerInterface
     */
    public function getHandler()
    {
        return $this->handler;
    }

    /**
     * @param null|HandlerInterface $handler
     *
     * @return $this
     */
    public function setHandler($handler)
    {
        $this->handler = $handler;

        return $this;
    }

    public function redirectToIndex()
    {
        return $this->redirect(
            $this->generateUrl(sprintf('%s_index', $this->getResourcePathPrefix()))
        );
    }

    public function deleteAction(Request $request)
    {
        $entity = $this->findOr404($request);

        $this->handler->delete($entity, true);

        return $this->redirectToIndex();
    }
}
