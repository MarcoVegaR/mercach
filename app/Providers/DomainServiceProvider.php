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
        $this->app->bind(
            \App\Contracts\Repositories\LocalTypeRepositoryInterface::class,
            \App\Repositories\LocalTypeRepository::class
        );

        $this->app->bind(
            \App\Contracts\Repositories\LocalStatusRepositoryInterface::class,
            \App\Repositories\LocalStatusRepository::class
        );

        $this->app->bind(
            \App\Contracts\Repositories\TradeCategoryRepositoryInterface::class,
            \App\Repositories\TradeCategoryRepository::class
        );

        $this->app->bind(
            \App\Contracts\Repositories\ConcessionaireTypeRepositoryInterface::class,
            \App\Repositories\ConcessionaireTypeRepository::class
        );

        $this->app->bind(
            \App\Contracts\Repositories\DocumentTypeRepositoryInterface::class,
            \App\Repositories\DocumentTypeRepository::class
        );

        $this->app->bind(
            \App\Contracts\Repositories\ContractTypeRepositoryInterface::class,
            \App\Repositories\ContractTypeRepository::class
        );

        $this->app->bind(
            \App\Contracts\Repositories\ContractStatusRepositoryInterface::class,
            \App\Repositories\ContractStatusRepository::class
        );

        $this->app->bind(
            \App\Contracts\Repositories\ContractModalityRepositoryInterface::class,
            \App\Repositories\ContractModalityRepository::class
        );

        $this->app->bind(
            \App\Contracts\Repositories\ExpenseTypeRepositoryInterface::class,
            \App\Repositories\ExpenseTypeRepository::class
        );

        $this->app->bind(
            \App\Contracts\Repositories\PaymentStatusRepositoryInterface::class,
            \App\Repositories\PaymentStatusRepository::class
        );

        $this->app->bind(
            \App\Contracts\Repositories\BankRepositoryInterface::class,
            \App\Repositories\BankRepository::class
        );

        $this->app->bind(
            \App\Contracts\Repositories\PhoneAreaCodeRepositoryInterface::class,
            \App\Repositories\PhoneAreaCodeRepository::class
        );

        $this->app->bind(
            \App\Contracts\Repositories\PaymentTypeRepositoryInterface::class,
            \App\Repositories\PaymentTypeRepository::class
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
        $this->app->bind(
            \App\Contracts\Services\LocalTypeServiceInterface::class,
            \App\Services\LocalTypeService::class
        );

        $this->app->bind(\App\Services\LocalTypeService::class, function (\Illuminate\Contracts\Container\Container $app) {
            return new \App\Services\LocalTypeService(
                $app->make(\App\Contracts\Repositories\LocalTypeRepositoryInterface::class),
                $app
            );
        });

        $this->app->bind(
            \App\Contracts\Services\LocalStatusServiceInterface::class,
            \App\Services\LocalStatusService::class
        );

        $this->app->bind(\App\Services\LocalStatusService::class, function (\Illuminate\Contracts\Container\Container $app) {
            return new \App\Services\LocalStatusService(
                $app->make(\App\Contracts\Repositories\LocalStatusRepositoryInterface::class),
                $app
            );
        });

        $this->app->bind(
            \App\Contracts\Services\TradeCategoryServiceInterface::class,
            \App\Services\TradeCategoryService::class
        );

        $this->app->bind(\App\Services\TradeCategoryService::class, function (\Illuminate\Contracts\Container\Container $app) {
            return new \App\Services\TradeCategoryService(
                $app->make(\App\Contracts\Repositories\TradeCategoryRepositoryInterface::class),
                $app
            );
        });

        $this->app->bind(
            \App\Contracts\Services\ConcessionaireTypeServiceInterface::class,
            \App\Services\ConcessionaireTypeService::class
        );

        $this->app->bind(\App\Services\ConcessionaireTypeService::class, function (\Illuminate\Contracts\Container\Container $app) {
            return new \App\Services\ConcessionaireTypeService(
                $app->make(\App\Contracts\Repositories\ConcessionaireTypeRepositoryInterface::class),
                $app
            );
        });

        $this->app->bind(
            \App\Contracts\Services\DocumentTypeServiceInterface::class,
            \App\Services\DocumentTypeService::class
        );

        $this->app->bind(\App\Services\DocumentTypeService::class, function (\Illuminate\Contracts\Container\Container $app) {
            return new \App\Services\DocumentTypeService(
                $app->make(\App\Contracts\Repositories\DocumentTypeRepositoryInterface::class),
                $app
            );
        });

        $this->app->bind(
            \App\Contracts\Services\ContractTypeServiceInterface::class,
            \App\Services\ContractTypeService::class
        );

        $this->app->bind(\App\Services\ContractTypeService::class, function (\Illuminate\Contracts\Container\Container $app) {
            return new \App\Services\ContractTypeService(
                $app->make(\App\Contracts\Repositories\ContractTypeRepositoryInterface::class),
                $app
            );
        });

        $this->app->bind(
            \App\Contracts\Services\ContractStatusServiceInterface::class,
            \App\Services\ContractStatusService::class
        );

        $this->app->bind(\App\Services\ContractStatusService::class, function (\Illuminate\Contracts\Container\Container $app) {
            return new \App\Services\ContractStatusService(
                $app->make(\App\Contracts\Repositories\ContractStatusRepositoryInterface::class),
                $app
            );
        });

        $this->app->bind(
            \App\Contracts\Services\ContractModalityServiceInterface::class,
            \App\Services\ContractModalityService::class
        );

        $this->app->bind(\App\Services\ContractModalityService::class, function (\Illuminate\Contracts\Container\Container $app) {
            return new \App\Services\ContractModalityService(
                $app->make(\App\Contracts\Repositories\ContractModalityRepositoryInterface::class),
                $app
            );
        });

        $this->app->bind(
            \App\Contracts\Services\ExpenseTypeServiceInterface::class,
            \App\Services\ExpenseTypeService::class
        );

        $this->app->bind(\App\Services\ExpenseTypeService::class, function (\Illuminate\Contracts\Container\Container $app) {
            return new \App\Services\ExpenseTypeService(
                $app->make(\App\Contracts\Repositories\ExpenseTypeRepositoryInterface::class),
                $app
            );
        });

        $this->app->bind(
            \App\Contracts\Services\PaymentStatusServiceInterface::class,
            \App\Services\PaymentStatusService::class
        );

        $this->app->bind(\App\Services\PaymentStatusService::class, function (\Illuminate\Contracts\Container\Container $app) {
            return new \App\Services\PaymentStatusService(
                $app->make(\App\Contracts\Repositories\PaymentStatusRepositoryInterface::class),
                $app
            );
        });

        $this->app->bind(
            \App\Contracts\Services\BankServiceInterface::class,
            \App\Services\BankService::class
        );

        $this->app->bind(\App\Services\BankService::class, function (\Illuminate\Contracts\Container\Container $app) {
            return new \App\Services\BankService(
                $app->make(\App\Contracts\Repositories\BankRepositoryInterface::class),
                $app
            );
        });

        $this->app->bind(
            \App\Contracts\Services\PhoneAreaCodeServiceInterface::class,
            \App\Services\PhoneAreaCodeService::class
        );

        $this->app->bind(\App\Services\PhoneAreaCodeService::class, function (\Illuminate\Contracts\Container\Container $app) {
            return new \App\Services\PhoneAreaCodeService(
                $app->make(\App\Contracts\Repositories\PhoneAreaCodeRepositoryInterface::class),
                $app
            );
        });

        $this->app->bind(
            \App\Contracts\Services\PaymentTypeServiceInterface::class,
            \App\Services\PaymentTypeService::class
        );

        $this->app->bind(\App\Services\PaymentTypeService::class, function (\Illuminate\Contracts\Container\Container $app) {
            return new \App\Services\PaymentTypeService(
                $app->make(\App\Contracts\Repositories\PaymentTypeRepositoryInterface::class),
                $app
            );
        });

        $this->app->bind('exporter.csv', \App\Exports\CsvExporter::class);
        $this->app->bind('exporter.xlsx', \App\Exports\XlsxExporter::class);
        $this->app->bind('exporter.json', \App\Exports\JsonExporter::class);
    }
}
