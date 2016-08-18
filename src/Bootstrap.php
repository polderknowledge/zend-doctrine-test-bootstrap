<?php

namespace PolderKnowledge\TestBootstrap;

use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\Common\Persistence\Mapping\RuntimeReflectionService;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Tools\SchemaTool;
use DoctrineDataFixtureModule\Command\ImportCommand;
use DoctrineDataFixtureModule\Loader\ServiceLocatorAwareLoader;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\NullOutput;
use Zend\ModuleManager\ModuleManager;
use Zend\Mvc\Application;
use Doctrine\ORM\EntityManagerInterface;
use Zend\ServiceManager\ServiceManager;
use Zend\Stdlib\ArrayUtils;

/**
 * Singleton class to bootstrap Zend Framework
 */
class Bootstrap
{
    static private $application;

    public static function getApplication()
    {
        if (self::$application === null) {
            self::$application = self::buildApplication();
        }

        return self::$application;
    }

    /**
     * @return Application
     */
    private static function buildApplication()
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
        self::createSchema($entityManager);

        echo "Initializing database content\n";
        self::initDatabase($serviceManager, $entityManager);

        echo "PHPUnit bootstrap complete\n";
        return $application;
    }

    private static function dropTables(EntityManagerInterface $entityManager)
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

    private static function createSchema(EntityManager $entityManager)
    {
        echo "Generating schema\n";
        passthru('php public/index.php orm:schema-tool:create');
        echo "Finished generating schema\n";
        return;

        /*
         * Does not work in some edge cases
         * Better use Doctrine\Common\DataFixtures\Purger\ORMPurger but that might not be available in every project
         *
        $metaDataDriver = $entityManager->getConfiguration()->getMetadataDriverImpl();
        $namingStrategy = $entityManager->getConfiguration()->getNamingStrategy();

        $allClassMetaData = [];

        foreach ($metaDataDriver->getAllClassNames() as $className) {
            $metaData = new ClassMetadata($className, $namingStrategy);
            $metaData->initializeReflection(new RuntimeReflectionService);
            $metaDataDriver->loadMetadataForClass($className, $metaData);

            $allClassMetaData[] = $metaData;
        }

        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->createSchema($allClassMetaData);
        */
    }

    private static function initDatabase(ServiceManager $serviceManager, EntityManager $entityManager)
    {
        // check if DoctrineDataFixtureModule is present
        /** @var ModuleManager $moduleManager */
        $moduleManager = $serviceManager->get('ModuleManager');
        $moduleNames = array_keys($moduleManager->getLoadedModules());

        if (!in_array('DoctrineDataFixtureModule', $moduleNames)) {
            echo "DoctrineDataFixtureModule not enabled. Skipping database initialization";
            return;
        }

        $loader = new ServiceLocatorAwareLoader($serviceManager);
        $purger = new ORMPurger;

        $executor = new ORMExecutor($entityManager, $purger);

        foreach ($serviceManager->get('config')['doctrine']['fixture'] as $key => $value) {
            $loader->loadFromDirectory($value);
        }
        $executor->execute($loader->getFixtures());
    }
}