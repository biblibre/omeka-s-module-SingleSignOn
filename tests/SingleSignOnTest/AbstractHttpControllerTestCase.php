<?php declare(strict_types=1);

namespace SingleSignOnTest;

use Laminas\Test\PHPUnit\Controller\AbstractHttpControllerTestCase as LaminasAbstractHttpControllerTestCase;
use Omeka\Entity\User;
use Omeka\Mvc\Application;

/**
 * Enhanced HTTP controller test case with authentication helpers.
 *
 * Solves the problem where authentication doesn't persist across reset() calls
 * in Laminas controller tests.
 *
 * Adapted from CommonTest\AbstractHttpControllerTestCase (Daniel-KM/Omeka-S-module-Common).
 */
abstract class AbstractHttpControllerTestCase extends LaminasAbstractHttpControllerTestCase
{
    protected bool $requiresLogin = true;

    protected static ?User $adminUser = null;

    protected string $adminEmail = 'admin@example.com';

    public function setUp(): void
    {
        $config = require OMEKA_PATH . '/application/config/application.config.php';
        $reader = new \Laminas\Config\Reader\Ini;
        $testConfig = [
            'connection' => $reader->fromFile(OMEKA_PATH . '/application/test/config/database.ini'),
        ];
        $config = array_merge($config, $testConfig);
        $this->setApplicationConfig($config);

        parent::setUp();
    }

    public function getApplication()
    {
        if ($this->application) {
            return $this->application;
        }

        $appConfig = $this->applicationConfig;
        $this->application = Application::init($appConfig);

        $events = $this->application->getEventManager();
        $this->application->getServiceManager()->get('SendResponseListener')->detach($events);

        return $this->application;
    }

    public function dispatch($url, $method = null, $params = [], $isXmlHttpRequest = false): void
    {
        $this->reset();
        $this->getApplication();

        if ($this->requiresLogin) {
            $this->loginAsAdmin();
        }

        parent::dispatch($url, $method, $params, $isXmlHttpRequest);
    }

    protected function loginAsAdmin(): void
    {
        $services = $this->getApplicationServiceLocator();
        $auth = $services->get('Omeka\AuthenticationService');

        if ($auth->hasIdentity()) {
            return;
        }

        if (self::$adminUser === null) {
            $em = $services->get('Omeka\EntityManager');
            self::$adminUser = $em->getRepository(User::class)
                ->findOneBy(['email' => $this->adminEmail]);
        }

        if (self::$adminUser instanceof \Omeka\Entity\User) {
            $auth->getStorage()->write(self::$adminUser);
            $services->get('Omeka\Settings\User')
                ->setTargetId(self::$adminUser->getId());
        }
    }

    protected function loginWithCredentials(string $email, string $password): bool
    {
        $services = $this->getApplicationServiceLocator();
        $auth = $services->get('Omeka\AuthenticationService');

        $adapter = $auth->getAdapter();
        $adapter->setIdentity($email);
        $adapter->setCredential($password);

        $result = $auth->authenticate();
        return $result->isValid();
    }

    protected function logout(): void
    {
        $services = $this->getApplicationServiceLocator();
        $auth = $services->get('Omeka\AuthenticationService');
        $auth->clearIdentity();
    }

    public function dispatchUnauthenticated($url, $method = null, $params = [], $isXmlHttpRequest = false): void
    {
        $originalRequiresLogin = $this->requiresLogin;
        $this->requiresLogin = false;

        $this->reset();
        $this->getApplication();
        $this->logout();

        parent::dispatch($url, $method, $params, $isXmlHttpRequest);

        $this->requiresLogin = $originalRequiresLogin;
    }

    protected function api()
    {
        return $this->getApplicationServiceLocator()->get('Omeka\ApiManager');
    }

    protected function getEntityManager()
    {
        return $this->getApplicationServiceLocator()->get('Omeka\EntityManager');
    }
}
