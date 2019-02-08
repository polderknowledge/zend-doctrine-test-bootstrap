<?php

namespace PolderKnowledge\TestBootstrap;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

abstract class AbstractTest extends TestCase
{
    protected $application;

    /* @var ContainerInterface */
    protected $serviceManager;

    /* @var EntityManagerInterface */
    protected $entityManager;

    protected function setUp()
    {
        $this->application = Bootstrap::getApplication();
        $this->serviceManager = $this->application->getServiceManager();
        $this->entityManager = $this->serviceManager->get('doctrine.entitymanager.orm_default');
    }

    /**
     * For when you want to test your side-effects on another client
     * @return EntityManagerInterface
     */
    protected function createIndependentEntityManager()
    {
        return $this->serviceManager->create('doctrine.entitymanager.orm_default');
    }
}
