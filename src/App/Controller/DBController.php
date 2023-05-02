<?php

namespace App\Controllers;

use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\EntityManager;

class DbContext
{
    private static $_entityManager;
    public static function get_entity_manager(): EntityManager
    {
        if (!isset(self::$_entityManager)) {
            $isDevMode = true;
            $proxyDir = null;
            $cache = null;
            $useSimpleAnnotationReader = false;
            $config = Setup::createAnnotationMetadataConfiguration(array(__DIR__ . "/src"), $isDevMode, $proxyDir, $cache, $useSimpleAnnotationReader);
            // or if you prefer yaml or XML
            // $config = Setup::createXMLMetadataConfiguration(array(__DIR__."/config/xml"), $isDevMode);
            // $config = Setup::createYAMLMetadataConfiguration(array(__DIR__."/config/yaml"), $isDevMode);

            // database configuration parameters
            $conn = self::getConn();

            // obtaining the entity manager
            self::$_entityManager = EntityManager::create($conn, $config);
        }
        return self::$_entityManager;
    }

    // return database connection parameters
    public static function getConn()
    {
        $config = require __DIR__ . '/../../../config.php';
        $conn =  array_merge(['driver' => 'pdo_mysql'], $config['mysql']);
        $conn['dbname'] = $conn['database'];
        return $conn;
        // $conn = array(
        //     'driver'   => 'pdo_mysql',
        //     'host'     => 'localhost',
        //     'dbname'   => 'survey',
        //     'user'     => 'root',
        //     'password' => 'P4$$w0rd123'
        // );
        // return $conn;
    }
}
