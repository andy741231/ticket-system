<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\PermissionRegistrar;
use App\Models\App as AppModel;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Register a callback to run after database refresh (for tests using RefreshDatabase)
        if (method_exists($this, 'afterRefreshingDatabase')) {
            $this->afterRefreshingDatabase(function () {
                $this->initializeTeamContext();
            });
        }

        // For tests not using RefreshDatabase, try to initialize immediately if schema is ready
        if (Schema::hasTable('apps')) {
            $this->initializeTeamContext();
        }
    }

    protected function initializeTeamContext(): void
    {
        if (! Schema::hasTable('apps')) {
            return;
        }

        $app = AppModel::first();
        if (! $app) {
            $app = AppModel::create([
                'slug' => 'test-app',
                'name' => 'Test App',
            ]);
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($app->id);
    }
}
