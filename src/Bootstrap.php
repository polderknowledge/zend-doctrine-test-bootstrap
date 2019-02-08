<?php

namespace PolderKnowledge\TestBootstrap;

use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use DoctrineDataFixtureModule\Command\ImportCommand;
use DoctrineDataFixtureModule\Loader\ServiceLocatorAwareLoader;
use Psr\Container\ContainerInterface;
use Zend\ModuleManager\ModuleManager;
use Zend\Mvc\Application;
use Zend\Stdlib\ArrayUtils;

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

    private static function buildApplication(): Application
    {
        $mergedApplicationConfig = ArrayUtils::merge(
            require 'config/application.config.php',
            require 'config/development.config.php'
        );

        error_reporting(E_ALL & ~E_USER_DEPRECATED);

        echo "Calling Zend\\Mvc\\Application::init()\n";
        $application = \Zend\Mvc\Application::init($mergedApplicationConfig);
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
        self::createSchema();

        echo "Initializing database content\n";
        self::initDatabase($serviceManager, $entityManager);

        echo "PHPUnit bootstrap complete\n";
        return $application;
    }

    private static function dropTables(EntityManagerInterface $entityManager): void
    {
        $connection = $entityManager->getConnection();

        $statement = $connection->prepare('SHOW TABLES');
        $statement->execute();
        $tables = $statement->fetchAll();

        $connection->exec('SET foreign_key_checks = 0');
        echo "Dropping database\n";
        foreach ($tables as $table)
        {
            $tableName = current($table);
            $connection->exec('DROP TABLE `' . $tableName . '`');
        }

        $connection->exec('SET foreign_key_checks = 1');
    }

    private static function createSchema(): void
    {
        echo "Generating schema\n";

        passthru('php public/index.php orm:schema-tool:create');

        echo "Finished generating schema\n";
    }

    private static function initDatabase(ContainerInterface $container, EntityManagerInterface $entityManager): void
    {
        // check if DoctrineDataFixtureModule is present
        /** @var ModuleManager $moduleManager */
        $moduleManager = $container->get('ModuleManager');
        $moduleNames = array_keys($moduleManager->getLoadedModules());

        if (!in_array('DoctrineDataFixtureModule', $moduleNames)) {
            echo "DoctrineDataFixtureModule not enabled. Skipping database initialization";
            return;
        }

        $loader = new ServiceLocatorAwareLoader($container);
        $purger = new ORMPurger;

        $executor = new ORMExecutor($entityManager, $purger);

        foreach ($container->get('config')['doctrine']['fixture'] as $key => $value) {
            $loader->loadFromDirectory($value);
        }
        $executor->execute($loader->getFixtures());
    }
}
