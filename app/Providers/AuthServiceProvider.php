<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Audit;
use App\Models\Role;
use App\Models\User;
use App\Policies\AuditPolicy;
use App\Policies\RolePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

/**
 * Authorization Service Provider.
 *
 * Handles policy registration and global authorization rules.
 *
 * @author Laravel Boilerplate
 */
class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Role::class => RolePolicy::class,
        Audit::class => AuditPolicy::class,
        \App\Models\User::class => \App\Policies\UserPolicy::class,
        \App\Models\LocalType::class => \App\Policies\LocalTypePolicy::class,
        \App\Models\LocalStatus::class => \App\Policies\LocalStatusPolicy::class,
        \App\Models\TradeCategory::class => \App\Policies\TradeCategoryPolicy::class,
        \App\Models\ConcessionaireType::class => \App\Policies\ConcessionaireTypePolicy::class,
        \App\Models\DocumentType::class => \App\Policies\DocumentTypePolicy::class,
        \App\Models\ContractType::class => \App\Policies\ContractTypePolicy::class,
        \App\Models\ContractStatus::class => \App\Policies\ContractStatusPolicy::class,
        \App\Models\ContractModality::class => \App\Policies\ContractModalityPolicy::class,
        \App\Models\ExpenseType::class => \App\Policies\ExpenseTypePolicy::class,
        \App\Models\PaymentStatus::class => \App\Policies\PaymentStatusPolicy::class,
        \App\Models\Bank::class => \App\Policies\BankPolicy::class,
        \App\Models\PhoneAreaCode::class => \App\Policies\PhoneAreaCodePolicy::class,
        \App\Models\PaymentType::class => \App\Policies\PaymentTypePolicy::class,
        \App\Models\Market::class => \App\Policies\MarketPolicy::class,
        \App\Models\LocalLocation::class => \App\Policies\LocalLocationPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Register policies
        $this->registerPolicies();

        // Explicitly register the Role policy
        Gate::policy(\Spatie\Permission\Models\Role::class, \App\Policies\RolePolicy::class);

        // Optional: Global super-admin bypass
        // Uncomment the following if you want super-admin role to bypass all gates
        /*
        Gate::before(function (User $user, string $ability) {
            // Super-admin bypasses all authorization checks
            // Return true to allow, false to deny, null to fall through to normal checks
            return $user->hasRole('super-admin') ? true : null;
        });
        */

        // Alternative: Register policies programmatically
        // This approach is useful when you have many models following a pattern
        /*
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);
        // ... more policies
        */
    }
}
