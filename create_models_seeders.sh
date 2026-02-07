#!/bin/bash
export COMPOSER_ALLOW_SUPERUSER=1

# Create Models
php artisan make:model -f Modules/Hosting/Models/Plan
php artisan make:model -f Modules/Hosting/Models/Subscription
php artisan make:model -f Modules/Domain/Models/Domain
php artisan make:model -f Modules/Domain/Models/Subdomain

# Create Seeders
php artisan make:seeder AdminSeeder
php artisan make:seeder PlanSeeder

echo "Models and Seeders created!"
