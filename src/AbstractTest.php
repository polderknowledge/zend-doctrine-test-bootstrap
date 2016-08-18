<?php

namespace PolderKnowledge\TestBootstrap;

class AbstractTest extends \PHPUnit_Framework_TestCase
{
    protected $application;

    /* @var \Zend\ServiceManager\ServiceManager */
    protected $serviceManager;

    /* @var \Doctrine\ORM\EntityManagerInterface */
    protected $entityManager;

    protected function setUp()
    {
        $this->application = Bootstrap::getApplication();
        $this->serviceManager = $this->application->getServiceManager();
        $this->entityManager = $this->serviceManager->get('doctrine.entitymanager.orm_default');
    }

    /**
     * For when you want to test your side-effects on another client
     * @return \Doctrine\ORM\EntityManagerInterface
     */
    protected function createIndependentEntityManager()
    {
        return $this->serviceManager->create('doctrine.entitymanager.orm_default');
    }
}
