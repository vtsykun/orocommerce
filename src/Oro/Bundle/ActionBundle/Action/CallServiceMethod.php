<?php

namespace Oro\Bundle\ActionBundle\Action;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\PropertyAccess\PropertyPathInterface;

use Oro\Bundle\ActionBundle\Exception\InvalidParameterException;
use Oro\Bundle\WorkflowBundle\Model\Action\AbstractAction;
use Oro\Bundle\WorkflowBundle\Model\ContextAccessor;

class CallServiceMethod extends AbstractAction
{
    /** @var ContainerInterface */
    protected $container;

    /** @var array */
    protected $options;

    /**
     * @param ContextAccessor $contextAccessor
     * @param ContainerInterface $container
     */
    public function __construct(ContextAccessor $contextAccessor, ContainerInterface $container)
    {
        parent::__construct($contextAccessor);

        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     */
    public function initialize(array $options)
    {
        if (empty($options['service'])) {
            throw new InvalidParameterException('Service name parameter is required');
        }

        if (!$this->container->has($options['service'])) {
            throw new InvalidParameterException(sprintf('Undefined service with name "%s"', $options['service']));
        }

        if (empty($options['method'])) {
            throw new InvalidParameterException('Method name parameter is required');
        }

        if (!method_exists($this->container->get($options['service']), $options['method'])) {
            throw new InvalidParameterException(
                sprintf('Could not found public method "%s" in service "%s"', $options['method'], $options['service'])
            );
        }

        $this->options = $options;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function executeAction($context)
    {
        $service = $this->getService();
        $method = $this->getMethod();
        $callback = [$service, $method];
        $parameters = $this->getMethodParameters($context);

        $result = call_user_func_array($callback, $parameters);

        $attribute = $this->getAttribute();
        if ($attribute) {
            $this->contextAccessor->setValue($context, $attribute, $result);
        }
    }

    /**
     * @return PropertyPathInterface|null
     */
    protected function getAttribute()
    {
        return $this->getOption($this->options, 'attribute', null);
    }

    /**
     * @return string
     */
    protected function getService()
    {
        return $this->container->get($this->options['service']);
    }

    /**
     * @return string
     */
    protected function getMethod()
    {
        return $this->options['method'];
    }

    /**
     * @param mixed $context
     * @return array
     */
    protected function getMethodParameters($context)
    {
        $parameters = $this->getOption($this->options, 'method_parameters', []);

        foreach ($parameters as $name => $value) {
            $parameters[$name] = $this->contextAccessor->getValue($context, $value);
        }

        return $parameters;
    }
}
