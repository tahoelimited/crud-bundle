<?php

namespace Tahoe\Bundle\CrudBundle\Twig;

use Doctrine\ORM\PersistentCollection;
use Symfony\Component\Routing\RouterInterface;

class TahoeCrudExtension extends \Twig_Extension
{
    protected $router;

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction('tahoe_crud_property', array(
                $this,
                'crudPropertyShow'
            ), array('is_safe' => array('html'))),
        );
    }

    public function getName()
    {
        return 'tahoe_crud_extension';
    }

    public function crudPropertyShow($mainEntity, $property)
    {
        $property = (property_exists($mainEntity, $property) ?
            call_user_func(array($mainEntity, sprintf('get%s', ucwords($property)))) : $property);
        $htmlProperty = "";

        if (is_array($property) || $property instanceof PersistentCollection) {
            $htmlProperty .= "<ul>";
            foreach ($property as $prop) {
                $htmlProperty .= sprintf("<li>%s</li>", $this->crudPropertyShow($mainEntity, $prop));
            }
            $htmlProperty .= "</ul>";

            return $htmlProperty;
        }

        if (is_bool($property)) {
            $class = $property ? "check" : "times";
            return sprintf("<i class='fa fa-%s'></i> ", $class);
        }


        if (is_object($property)) {
            if ($property instanceof \DateTime) {
                return $property->format('r');
            }
            $entityName = get_class($property);
            $resource = substr($entityName, strrpos($entityName, '\\') + 1);
            $resource = implode("_", preg_split("/(?<=[a-z])(?![a-z])/", $resource, -1, PREG_SPLIT_NO_EMPTY));

            try {
                if (method_exists($property, 'getId')) {
                    $url = $this->router->generate(
                        str_replace(" ", "_", strtolower($resource)) . "_show",
                        array('id' => $property->getId())
                    );
                    return sprintf('<a href="%s">%s</a>', $url, $property);
                }
            } catch (\Exception $e) {
                // do nothing
            }
        }

        return $property;
    }
}
