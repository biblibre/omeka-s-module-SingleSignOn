<?php declare(strict_types=1);

namespace SingleSignOnTest;

use Laminas\Config\Reader\Ini;
use Omeka\Module\Manager as ModuleManager;
use Omeka\Mvc\Application;
use Omeka\Test\DbTestCase;

/**
 * Bootstrap helper for SingleSignOn module tests.
 *
 * Adapted from CommonTest\Bootstrap (Daniel-KM/Omeka-S-module-Common).
 */
class Bootstrap
{
    protected static ?array $config = null;

    public static function bootstrap(
        array $modules = [],
        ?string $testNamespace = null,
        ?string $testPath = null,
        bool $verbose = true
    ): void {
        require_once dirname(__DIR__, 3) . '/bootstrap.php';

        $loader = new \Composer\Autoload\ClassLoader();

        if ($testNamespace && $testPath) {
            $moduleRoot = dirname($testPath, 2);

            $moduleVendorAutoload = $moduleRoot . '/vendor/autoload.php';
            if (file_exists($moduleVendorAutoload)) {
                require_once $moduleVendorAutoload;
            }

            $composerFile = $moduleRoot . '/composer.json';
            if (file_exists($composerFile)) {
                $composer = json_decode(file_get_contents($composerFile), true);

                if (!empty($composer['autoload']['psr-4'])) {
                    foreach ($composer['autoload']['psr-4'] as $ns => $path) {
                        $loader->addPsr4($ns, $moduleRoot . '/' . $path);
                    }
                }

                if (!empty($composer['autoload-dev']['psr-4'])) {
                    foreach ($composer['autoload-dev']['psr-4'] as $ns => $path) {
                        $loader->addPsr4($ns, $moduleRoot . '/' . $path);
                    }
                }
            } else {
                $loader->addPsr4($testNamespace . '\\', $testPath);
                $moduleNamespace = preg_replace('/Test$/', '', $testNamespace);
                if ($moduleNamespace !== $testNamespace) {
                    $modulePath = $moduleRoot . '/src/';
                    if (is_dir($modulePath)) {
                        $loader->addPsr4($moduleNamespace . '\\', $modulePath);
                    }
                }
            }
        }

        $loader->register(true);

        error_reporting(E_ALL);
        ini_set('display_errors', '1');

        if ($verbose) {
            self::log('Dropping test database schema…');
        }
        DbTestCase::dropSchema();

        if ($verbose) {
            self::log('Creating test database schema…');
        }
        DbTestCase::installSchema();

        self::$config = self::buildConfig();

        if (!empty($modules)) {
            if ($verbose) {
                self::log('Installing required modules…');
            }
            self::installModules($modules, $verbose);
        }

        if ($verbose) {
            self::log('Test database ready.');
        }
    }

    public static function buildConfig(): array
    {
        if (self::$config) {
            return self::$config;
        }

        $reader = new Ini();
        $config = require OMEKA_PATH . '/application/config/application.config.php';
        self::$config = array_merge($config, [
            'connection' => $reader->fromFile(OMEKA_PATH . '/application/test/config/database.ini'),
        ]);

        return self::$config;
    }

    public static function getConfig(): array
    {
        if (!self::$config) {
            self::$config = self::buildConfig();
        }
        return self::$config;
    }

    public static function getApplication(): Application
    {
        return Application::init(self::getConfig());
    }

    public static function installModules(array $modules, bool $verbose = true): void
    {
        foreach ($modules as $moduleName) {
            $isOptional = str_starts_with($moduleName, '?');
            if ($isOptional) {
                $moduleName = substr($moduleName, 1);
            }

            $application = Application::init(self::getConfig());
            $serviceLocator = $application->getServiceManager();

            $auth = $serviceLocator->get('Omeka\AuthenticationService');
            $adapter = $auth->getAdapter();
            $adapter->setIdentity('admin@example.com');
            $adapter->setCredential('root');
            $auth->authenticate();

            $moduleManager = $serviceLocator->get('Omeka\ModuleManager');
            $entityManager = $serviceLocator->get('Omeka\EntityManager');

            $module = $moduleManager->getModule($moduleName);
            if ($module && $module->getState() === ModuleManager::STATE_NOT_INSTALLED) {
                if ($verbose) {
                    self::log('  Installing module: ' . $moduleName . ($isOptional ? ' (optional)' : ''));
                }
                $moduleManager->install($module);
                $entityManager->flush();
                $entityManager->clear();
            } elseif ($module && $module->getState() === ModuleManager::STATE_NOT_ACTIVE) {
                if ($verbose) {
                    self::log('  Activating module: ' . $moduleName . ($isOptional ? ' (optional)' : ''));
                }
                $moduleManager->activate($module);
                $entityManager->flush();
                $entityManager->clear();
            } elseif (!$module && $verbose && !$isOptional) {
                self::log('  Warning: Module ' . $moduleName . ' not found');
            }
        }
    }

    protected static function log(string $message): void
    {
        file_put_contents('php://stdout', $message . "\n");
    }
}
