<?php

namespace PolderKnowledge\TestBootstrap;

use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\Common\Persistence\Mapping\RuntimeReflectionService;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Tools\SchemaTool;
use DoctrineDataFixtureModule\Command\ImportCommand;
use DoctrineDataFixtureModule\Loader\ServiceLocatorAwareLoader;
use Psr\Container\ContainerInterface;
use Zend\ModuleManager\ModuleManager;
use Zend\Mvc\Application;
use Zend\ServiceManager\ServiceManager;
use Zend\Stdlib\ArrayUtils;
use ZF\Doctrine\DataFixture\DataFixtureManager;

/**
 * Singleton class to bootstrap Zend Framework
 */
class Bootstrap
{
    /** @var Application */
    private static $application;

    public static function getApplication(): Application
    {
        if (self::$application === null) {
            self::$application = self::buildApplication();
        }

        return self::$application;
    }

    private static function getApplicationConfig()
    {
        $applicationConfig = require 'config/application.config.php';

        if (file_exists('config/development.config.php')) {
            $applicationConfig = ArrayUtils::merge($applicationConfig, require 'config/development.config.php');
        }

        if (file_exists('config/test.config.php')) {
            $applicationConfig = ArrayUtils::merge($applicationConfig, require 'config/test.config.php');
        }

        return $applicationConfig;
    }

    private static function buildApplication(): Application
    {
        error_reporting(E_ALL & ~E_USER_DEPRECATED);

        echo "Calling Zend\\Mvc\\Application::init()\n";
        $application = \Zend\Mvc\Application::init(self::getApplicationConfig());
        $serviceManager = $application->getServiceManager();

        $config = $serviceManager->get('config');

        if (isset($config['unittest']['skip_database_reset']) && $config['unittest']['skip_database_reset'] === true) {
            echo "Skipping database reset\n";
            echo "PHPUnit bootstrap complete\n";
            return $application;
        }

        echo "Connecting to database\n";
        $entityManager = $serviceManager->get('doctrine.entitymanager.orm_default');

        echo "Dropping all tables\n";
        self::dropTables($entityManager);

        echo "Creating all tables\n";
        self::createSchema($entityManager);

        echo "Initializing database content\n";
        self::initDatabase($serviceManager, $entityManager);

        echo "PHPUnit bootstrap complete\n";
        return $application;
    }

    private static function dropTables(EntityManagerInterface $entityManager): void
    {
        $connection = $entityManager->getConnection();

        $statement = $connection->prepare('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"');
        $statement->execute();
        $tables = $statement->fetchAll();

        $connection->exec('SET foreign_key_checks = 0');
        echo "Dropping tables\n";
        foreach ($tables as $table)
        {
            $tableName = current($table);
            $connection->exec('DROP TABLE `' . $tableName . '`');
        }

        $statement = $connection->prepare('SHOW FULL TABLES WHERE Table_type = "VIEW"');
        $statement->execute();
        $views = $statement->fetchAll();

        echo "Dropping views\n";
        foreach ($views as $view)
        {
            $viewName = current($view);
            $connection->exec('DROP VIEW `' . $viewName . '`');
        }

        $connection->exec('SET foreign_key_checks = 1');
    }

    private static function createSchema(EntityManager $entityManager): void
    {
        echo "Generating schema\n";

        $metaDatas = $entityManager->getMetadataFactory()->getAllMetadata();

        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->createSchema($metaDatas);


        echo "Finished generating schema\n";
    }

    private static function initDatabase(ContainerInterface $container, EntityManagerInterface $entityManager): void
    {
        // check if DoctrineDataFixtureModule is present
        /** @var ModuleManager $moduleManager */
        $moduleManager = $container->get('ModuleManager');
        $moduleNames = array_keys($moduleManager->getLoadedModules());

        if (!in_array('ZF\\Doctrine\\DataFixture', $moduleNames)) {
            echo "ZF\\Doctrine\\DataFixture module is not present. Skipping database initialization";
            return;
        }

        foreach (self::getFixtureGroupNames($container) as $groupName) {
            self::executeFixtureGroup($container, $groupName);
        }
    }
    
    private static function getFixtureGroupNames(ContainerInterface $container): array
    {
        $fixtureConfig = $container->get('config')['doctrine']['fixture'];
        $fixtureGroups = array_keys($fixtureConfig);
        return $fixtureGroups;
    }
    
    private static function executeFixtureGroup(ServiceManager $serviceManager, string $groupName)
    {
        echo "Executing fixture group $groupName";

        /** @var DataFixtureManager $dataFixtureManager */
        $dataFixtureManager = $serviceManager->build(DataFixtureManager::class, [
            'group' => $groupName,
        ]);

        $loader = new Loader();
        foreach ($dataFixtureManager->getAll() as $fixture) {
            $loader->addFixture($fixture);
        }

        $executor = new ORMExecutor($dataFixtureManager->getObjectManager(), new ORMPurger());
        $executor->execute($loader->getFixtures(), true);
    }
}
