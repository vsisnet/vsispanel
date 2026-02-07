<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Database\Seeders;

use App\Modules\Marketplace\Models\AppTemplate;
use Illuminate\Database\Seeder;

class AppTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $apps = [
            [
                'name' => 'WordPress',
                'slug' => 'wordpress',
                'description' => 'The world\'s most popular content management system. Perfect for blogs, business websites, and online stores.',
                'version' => '6.7',
                'icon' => 'wordpress',
                'category' => 'cms',
                'type' => 'php',
                'requirements' => [
                    'php_version' => '7.4',
                    'extensions' => ['mysql', 'curl', 'gd', 'mbstring', 'xml', 'zip'],
                    'min_disk_mb' => 200,
                ],
            ],
            [
                'name' => 'Laravel',
                'slug' => 'laravel',
                'description' => 'A PHP web application framework with expressive, elegant syntax. Ideal for building modern web applications.',
                'version' => '11.x',
                'icon' => 'laravel',
                'category' => 'framework',
                'type' => 'php',
                'requirements' => [
                    'php_version' => '8.2',
                    'extensions' => ['bcmath', 'ctype', 'curl', 'dom', 'fileinfo', 'json', 'mbstring', 'openssl', 'pdo', 'tokenizer', 'xml'],
                    'min_disk_mb' => 300,
                ],
            ],
            [
                'name' => 'Joomla',
                'slug' => 'joomla',
                'description' => 'A free and open-source content management system for publishing web content.',
                'version' => '5.x',
                'icon' => 'joomla',
                'category' => 'cms',
                'type' => 'php',
                'requirements' => [
                    'php_version' => '8.1',
                    'extensions' => ['mysql', 'json', 'gd', 'mbstring', 'xml', 'zip'],
                    'min_disk_mb' => 200,
                ],
            ],
            [
                'name' => 'Drupal',
                'slug' => 'drupal',
                'description' => 'An open-source CMS for ambitious digital experiences. Build complex, content-rich websites.',
                'version' => '11.x',
                'icon' => 'drupal',
                'category' => 'cms',
                'type' => 'php',
                'requirements' => [
                    'php_version' => '8.3',
                    'extensions' => ['pdo', 'gd', 'curl', 'json', 'mbstring', 'xml', 'opcache'],
                    'min_disk_mb' => 300,
                ],
            ],
            [
                'name' => 'PrestaShop',
                'slug' => 'prestashop',
                'description' => 'A powerful and customizable open-source eCommerce platform for building online stores.',
                'version' => '8.x',
                'icon' => 'prestashop',
                'category' => 'ecommerce',
                'type' => 'php',
                'requirements' => [
                    'php_version' => '8.1',
                    'extensions' => ['curl', 'gd', 'intl', 'json', 'mbstring', 'openssl', 'pdo_mysql', 'xml', 'zip'],
                    'min_disk_mb' => 500,
                ],
            ],
            [
                'name' => 'Node.js Express',
                'slug' => 'express',
                'description' => 'Minimal and flexible Node.js web application framework with a starter template.',
                'version' => '4.x',
                'icon' => 'nodejs',
                'category' => 'framework',
                'type' => 'nodejs',
                'requirements' => [
                    'min_disk_mb' => 100,
                ],
            ],
        ];

        foreach ($apps as $app) {
            AppTemplate::updateOrCreate(
                ['slug' => $app['slug']],
                $app,
            );
        }
    }
}
