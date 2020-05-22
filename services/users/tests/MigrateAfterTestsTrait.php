<?php

namespace Tests;

trait MigrateAfterTestsTrait
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        echo "Migrating database\n";
        exec('APP_ENV="testing" php artisan migrate:refresh');
    }
}
