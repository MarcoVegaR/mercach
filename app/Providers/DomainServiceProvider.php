<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Service Provider para registro de bindings de repositorios y servicios de dominio.
 *
 * Centraliza el registro de interfaces hacia sus implementaciones concretas,
 * facilitando la inyección de dependencias y testing.
 */
class DomainServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->registerRepositories();
        $this->registerServices();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // No contextual bindings required; controllers inject concrete interfaces directly.
    }

    /**
     * Registra bindings de repositorios.
     *
     * Ejemplo de uso para repositorios concretos:
     *
     * $this->app->bind(
     *     \App\Contracts\Repositories\UserRepositoryInterface::class,
     *     \App\Repositories\UserRepository::class
     * );
     */
    private function registerRepositories(): void
    {
        // Placeholder para bindings de repositorios concretos
        // Los repositorios específicos deben registrarse aquí cuando se implementen

        // Ejemplo:
        // $this->app->bind(
        //     UserRepositoryInterface::class,
        //     UserRepository::class
        // );

        $this->app->bind(
            \App\Contracts\Repositories\RoleRepositoryInterface::class,
            \App\Repositories\RoleRepository::class
        );

        $this->app->bind(
            \App\Contracts\Repositories\AuditRepositoryInterface::class,
            \App\Repositories\AuditRepository::class
        );

        $this->app->bind(
            \App\Contracts\Repositories\UserRepositoryInterface::class,
            \App\Repositories\UserRepository::class
        );
    }

    /**
     * Registra bindings de servicios.
     *
     * Ejemplo de uso para servicios concretos:
     *
     * $this->app->bind(
     *     \App\Contracts\Services\UserServiceInterface::class,
     *     \App\Services\UserService::class
     * );
     */
    private function registerServices(): void
    {
        // Placeholder para bindings de servicios concretos
        // Los servicios específicos deben registrarse aquí cuando se implementen

        // Ejemplo:
        // $this->app->bind(
        //     RoleServiceInterface::class,
        //     RoleService::class
        // );

        $this->app->bind(
            \App\Contracts\Services\RoleServiceInterface::class,
            \App\Services\RoleService::class
        );

        $this->app->bind(
            \App\Contracts\Services\AuditServiceInterface::class,
            \App\Services\AuditService::class
        );

        // Register RoleService with its dependencies
        $this->app->bind(\App\Services\RoleService::class, function ($app) {
            return new \App\Services\RoleService(
                $app->make(\App\Contracts\Repositories\RoleRepositoryInterface::class),
                $app
            );
        });

        // Register AuditService with its dependencies
        $this->app->bind(\App\Services\AuditService::class, function ($app) {
            return new \App\Services\AuditService(
                $app->make(\App\Contracts\Repositories\AuditRepositoryInterface::class),
                $app
            );
        });

        // Register UserService with its dependencies
        $this->app->bind(\App\Contracts\Services\UserServiceInterface::class, \App\Services\UserService::class);
        $this->app->bind(\App\Services\UserService::class, function ($app) {
            return new \App\Services\UserService(
                $app->make(\App\Contracts\Repositories\UserRepositoryInterface::class),
                $app
            );
        });

        // Register exporters
        $this->app->bind('exporter.csv', \App\Exports\CsvExporter::class);
        $this->app->bind('exporter.xlsx', \App\Exports\XlsxExporter::class);
        $this->app->bind('exporter.json', \App\Exports\JsonExporter::class);
    }
}
