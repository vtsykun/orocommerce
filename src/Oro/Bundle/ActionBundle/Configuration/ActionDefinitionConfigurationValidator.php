<?php

namespace Oro\Bundle\ActionBundle\Configuration;

use Doctrine\Common\Collections\Collection;

use Psr\Log\LoggerInterface;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Routing\RouterInterface;

use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;

class ActionDefinitionConfigurationValidator
{
    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @var \Twig_ExistsLoaderInterface
     */
    protected $twigLoader;

    /**
     * @var DoctrineHelper
     */
    protected $doctrineHelper;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var bool
     */
    protected $debug;

    /**
     * @var Collection
     */
    protected $errors;

    /**
     * @param RouterInterface $router
     * @param \Twig_Loader_Filesystem $twigLoader
     * @param DoctrineHelper $doctrineHelper
     * @param LoggerInterface $logger
     * @param bool $debug
     */
    public function __construct(
        RouterInterface $router,
        \Twig_Loader_Filesystem $twigLoader,
        DoctrineHelper $doctrineHelper,
        LoggerInterface $logger,
        $debug
    ) {
        $this->router = $router;
        $this->twigLoader = $twigLoader;
        $this->doctrineHelper = $doctrineHelper;
        $this->logger = $logger;
        $this->debug = $debug;
    }

    /**
     * @param array $configuration
     * @param Collection $errors
     * @return array
     */
    public function validate(array $configuration, Collection $errors = null)
    {
        $this->errors = $errors;

        foreach ($configuration as $name => $action) {
            $this->validateAction($action, $name);
        }
    }

    /**
     * @param array $action
     * @param string $path
     */
    protected function validateAction(array $action, $path)
    {
        $this->validateFrontendOptions($action['frontend_options'], $this->getPath($path, 'frontend_options'));
        $this->validateRoutes($action['routes'], $this->getPath($path, 'routes'));
        $this->validateEntities($action['entities'], $this->getPath($path, 'entities'));
    }

    /**
     * @param array $options
     * @param string $path
     */
    protected function validateFrontendOptions(array $options, $path)
    {
        if (isset($options['template']) && !$this->twigLoader->exists($options['template'])) {
            $this->handleError(
                $this->getPath($path, 'template'),
                'Unable to find template "%s"',
                $options['template'],
                false
            );
        }
    }

    /**
     * @param array $items
     * @param string $path
     */
    protected function validateRoutes(array $items, $path)
    {
        $routeCollection = $this->router->getRouteCollection();

        foreach ($items as $key => $item) {
            if (!$routeCollection->get($item)) {
                $this->handleError($this->getPath($path, $key), 'Route "%s" not found.', $item);
            }
        }
    }

    /**
     * @param array $items
     * @param string $path
     */
    protected function validateEntities(array $items, $path)
    {
        foreach ($items as $key => $item) {
            if (!$this->validateEntity($item)) {
                $this->handleError($this->getPath($path, $key), 'Entity "%s" not found.', $item);
            }
        }
    }

    /**
     * @param string $entityName
     * @return boolean
     */
    protected function validateEntity($entityName)
    {
        try {
            $entityClass = $this->doctrineHelper->getEntityClass($entityName);

            if (!class_exists($entityClass, true)) {
                return false;
            }

            $reflection = new \ReflectionClass($entityClass);

            return $this->doctrineHelper->isManageableEntity($reflection->getName());
        } catch (\Exception $e) {
        }

        return false;
    }

    /**
     * @param string $path
     * @param string $subpath
     * @return string
     */
    protected function getPath($path, $subpath)
    {
        return $path . '.' . $subpath;
    }

    /**
     * @param string $path
     * @param string $message
     * @param mixed $value
     * @param bool $silent
     */
    protected function handleError($path, $message, $value, $silent = true)
    {
        $errorMessage = sprintf('%s: ' . $message, $path, $value);
        if ($this->debug) {
            $this->logger->warning('InvalidConfiguration: ' . $errorMessage, ['ActionConfiguration']);
        }

        if (!$silent) {
            throw new InvalidConfigurationException($errorMessage);
        }

        if ($this->errors !== null) {
            $this->errors->add($errorMessage);
        }
    }
}