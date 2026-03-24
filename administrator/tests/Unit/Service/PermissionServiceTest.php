<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Service;

use CB\Component\Contentbuilderng\Administrator\Service\PermissionService;
use CB\Component\Contentbuilderng\Tests\Stubs\Application;
use Joomla\CMS\Access\Access;
use Joomla\CMS\Factory;
use PHPUnit\Framework\TestCase;

final class PermissionServiceTest extends TestCase
{
    private PermissionService $service;
    private Application $app;

    protected function setUp(): void
    {
        $reflection = new \ReflectionClass(PermissionService::class);
        $this->service = $reflection->newInstanceWithoutConstructor();
        $this->app = new Application();
        $this->app->setIdentity(0, '', '');
        Factory::setApplication($this->app);
        Access::$groupsByUser = [
            0 => [9, 1],
        ];
    }

    public function testAuthorizeFeHonorsInheritedPublicGroupPermission(): void
    {
        $this->app->getSession()->set('com_contentbuilderng.permissions_fe', [
            'published' => true,
            1 => ['listaccess' => true],
        ]);

        self::assertTrue($this->service->authorizeFe('listaccess'));
    }

    public function testAuthorizeFeRejectsMissingInheritedPermission(): void
    {
        $this->app->getSession()->set('com_contentbuilderng.permissions_fe', [
            'published' => true,
            2 => ['listaccess' => true],
        ]);

        self::assertFalse($this->service->authorizeFe('listaccess'));
    }
}
