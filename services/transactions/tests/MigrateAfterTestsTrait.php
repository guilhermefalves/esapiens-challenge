<?php

namespace Tests;

trait MigrateAfterTestsTrait
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        echo "Migrating database\n";
        exec('php artisan migrate:fresh');
    }
}
