<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public const PERMISSIONS = [
        'settings.view',
        'settings.update',
        'users.view',
        'users.manage',
        'verifications.view',
        'verifications.review',
        'matches.view',
        'credits.adjust',
        'admin.manage',
        'audit.view',
    ];

    /**
     * Three roles.
     *
     * super_admin is the client. admin is a reviewer he may hire later, and
     * deliberately cannot reach settings — a person brought in to check
     * photographs has no business holding the payment gateway keys.
     * member is everyone else and holds no permissions at all: their access
     * to their own records runs through policies, not through this table.
     */
    public function run(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // Flush AFTER creating and BEFORE syncing. spatie caches the
        // permission table, and on a fresh database that cache is populated
        // (and empty) before this seeder inserts anything — so syncing
        // against it would silently assign nothing. This ordering is what
        // makes the seeder reliable on first run, not just on re-runs.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $superAdmin = Role::findOrCreate('super_admin', 'web');
        $superAdmin->syncPermissions(self::PERMISSIONS);

        $admin = Role::findOrCreate('admin', 'web');
        $admin->syncPermissions([
            'users.view',
            'verifications.view',
            'verifications.review',
            'matches.view',
            'credits.adjust',
        ]);

        Role::findOrCreate('member', 'web');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
