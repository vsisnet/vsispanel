<?php

declare(strict_types=1);

use App\Modules\Auth\Models\User;
use App\Modules\Domain\Models\Domain;
use App\Modules\FileManager\Services\FileManagerService;
use App\Modules\Hosting\Models\Plan;
use App\Modules\Hosting\Models\Subscription;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    // Create admin user
    $this->admin = User::factory()->create(['username' => 'adminuser']);
    $this->admin->assignRole('admin');

    // Create regular user with subscription and domain
    $this->user = User::factory()->create(['username' => 'testuser']);
    $this->user->assignRole('user');

    $this->plan = Plan::factory()->active()->create();
    $this->subscription = Subscription::factory()->active()->create([
        'user_id' => $this->user->id,
        'plan_id' => $this->plan->id,
    ]);

    $this->domain = Domain::factory()->create([
        'user_id' => $this->user->id,
        'subscription_id' => $this->subscription->id,
        'name' => 'example.com',
    ]);

    // Create another user for authorization tests
    $this->otherUser = User::factory()->create(['username' => 'otheruser']);
    $this->otherUser->assignRole('user');

    $this->otherSubscription = Subscription::factory()->active()->create([
        'user_id' => $this->otherUser->id,
        'plan_id' => $this->plan->id,
    ]);

    $this->otherDomain = Domain::factory()->create([
        'user_id' => $this->otherUser->id,
        'subscription_id' => $this->otherSubscription->id,
        'name' => 'other.com',
    ]);

    // Setup test directory
    $this->testBasePath = storage_path('app/test-files');
    config(['filemanager.base_path' => $this->testBasePath]);

    // Create test directory structure
    $domainPath = $this->testBasePath . '/example.com';
    File::makeDirectory($domainPath . '/public_html', 0755, true);
    File::put($domainPath . '/public_html/index.html', '<html><body>Test</body></html>');
    File::put($domainPath . '/public_html/style.css', 'body { color: red; }');
    File::makeDirectory($domainPath . '/public_html/js', 0755, true);
    File::put($domainPath . '/public_html/js/app.js', 'console.log("test");');

    $otherDomainPath = $this->testBasePath . '/other.com';
    File::makeDirectory($otherDomainPath . '/public_html', 0755, true);
    File::put($otherDomainPath . '/public_html/index.html', '<html><body>Other</body></html>');
});

afterEach(function () {
    // Clean up test directory
    if (File::isDirectory($this->testBasePath)) {
        File::deleteDirectory($this->testBasePath);
    }
});

describe('File Manager API - List Directory', function () {
    test('user can list their domain directory', function () {
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/domains/{$this->domain->id}/files");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'path',
                    'items',
                    'parent',
                ],
            ]);
    });

    test('user can list subdirectory', function () {
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/domains/{$this->domain->id}/files?path=public_html");

        $response->assertOk()
            ->assertJsonPath('success', true);

        $items = $response->json('data.items');
        expect(count($items))->toBeGreaterThan(0);
    });

    test('user cannot list another user domain directory', function () {
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/domains/{$this->otherDomain->id}/files");

        $response->assertForbidden();
    });

    test('admin can list any domain directory', function () {
        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/domains/{$this->otherDomain->id}/files");

        $response->assertOk();
    });

    test('unauthenticated user cannot list directory', function () {
        $response = $this->getJson("/api/v1/domains/{$this->domain->id}/files");

        $response->assertUnauthorized();
    });
});

describe('File Manager API - Get File Content', function () {
    test('user can get file content', function () {
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/domains/{$this->domain->id}/files/content?path=public_html/index.html");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'name',
                    'path',
                    'content',
                    'size',
                ],
            ]);

        expect($response->json('data.content'))->toContain('Test');
    });

    test('path is required', function () {
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/domains/{$this->domain->id}/files/content");

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'PATH_REQUIRED');
    });
});

describe('File Manager API - Save File Content', function () {
    test('user can save file content', function () {
        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/domains/{$this->domain->id}/files/content", [
                'path' => 'public_html/index.html',
                'content' => '<html><body>Updated</body></html>',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'File saved successfully.');

        // Verify file was saved
        $path = $this->testBasePath . '/example.com/public_html/index.html';
        expect(File::get($path))->toContain('Updated');
    });

    test('path and content are required', function () {
        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/domains/{$this->domain->id}/files/content", []);

        $response->assertStatus(422);
    });
});

describe('File Manager API - Create File', function () {
    test('user can create a new file', function () {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/domains/{$this->domain->id}/files/file", [
                'path' => 'public_html/new-file.txt',
                'content' => 'Hello World',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'File created successfully.');

        // Verify file was created
        $path = $this->testBasePath . '/example.com/public_html/new-file.txt';
        expect(File::exists($path))->toBeTrue();
        expect(File::get($path))->toBe('Hello World');
    });

    test('cannot create file with blocked extension', function () {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/domains/{$this->domain->id}/files/file", [
                'path' => 'public_html/script.exe',
                'content' => '',
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'CREATE_FAILED');
    });
});

describe('File Manager API - Create Directory', function () {
    test('user can create a new directory', function () {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/domains/{$this->domain->id}/files/directory", [
                'path' => 'public_html/new-folder',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Directory created successfully.');

        // Verify directory was created
        $path = $this->testBasePath . '/example.com/public_html/new-folder';
        expect(File::isDirectory($path))->toBeTrue();
    });
});

describe('File Manager API - Upload Files', function () {
    test('user can upload files', function () {
        $file = UploadedFile::fake()->create('test.txt', 100);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/domains/{$this->domain->id}/files/upload", [
                'path' => 'public_html',
                'files' => [$file],
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'uploaded',
                    'errors',
                ],
            ]);
    });

    test('cannot upload blocked file types', function () {
        $file = UploadedFile::fake()->create('test.exe', 100);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/domains/{$this->domain->id}/files/upload", [
                'path' => 'public_html',
                'files' => [$file],
            ]);

        // Should have errors for blocked file
        $errors = $response->json('data.errors');
        expect(count($errors))->toBeGreaterThan(0);
    });
});

describe('File Manager API - Rename', function () {
    test('user can rename a file', function () {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/domains/{$this->domain->id}/files/rename", [
                'path' => 'public_html/style.css',
                'new_name' => 'styles.css',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Renamed successfully.');

        // Verify file was renamed
        $oldPath = $this->testBasePath . '/example.com/public_html/style.css';
        $newPath = $this->testBasePath . '/example.com/public_html/styles.css';
        expect(File::exists($oldPath))->toBeFalse();
        expect(File::exists($newPath))->toBeTrue();
    });

    test('cannot rename with invalid filename', function () {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/domains/{$this->domain->id}/files/rename", [
                'path' => 'public_html/style.css',
                'new_name' => 'path/to/file.css',
            ]);

        $response->assertStatus(422);
    });
});

describe('File Manager API - Copy', function () {
    test('user can copy a file', function () {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/domains/{$this->domain->id}/files/copy", [
                'source' => 'public_html/index.html',
                'destination' => 'public_html/index-backup.html',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Copied successfully.');

        // Verify both files exist
        $sourcePath = $this->testBasePath . '/example.com/public_html/index.html';
        $destPath = $this->testBasePath . '/example.com/public_html/index-backup.html';
        expect(File::exists($sourcePath))->toBeTrue();
        expect(File::exists($destPath))->toBeTrue();
    });
});

describe('File Manager API - Move', function () {
    test('user can move a file', function () {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/domains/{$this->domain->id}/files/move", [
                'source' => 'public_html/style.css',
                'destination' => 'public_html/css/style.css',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Moved successfully.');

        // Verify file was moved
        $oldPath = $this->testBasePath . '/example.com/public_html/style.css';
        $newPath = $this->testBasePath . '/example.com/public_html/css/style.css';
        expect(File::exists($oldPath))->toBeFalse();
        expect(File::exists($newPath))->toBeTrue();
    });
});

describe('File Manager API - Delete', function () {
    test('user can delete a file', function () {
        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/domains/{$this->domain->id}/files", [
                'paths' => ['public_html/style.css'],
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Deleted successfully.');

        // Verify file was deleted
        $path = $this->testBasePath . '/example.com/public_html/style.css';
        expect(File::exists($path))->toBeFalse();
    });

    test('user can delete multiple files', function () {
        File::put($this->testBasePath . '/example.com/public_html/temp1.txt', 'temp');
        File::put($this->testBasePath . '/example.com/public_html/temp2.txt', 'temp');

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/domains/{$this->domain->id}/files", [
                'paths' => ['public_html/temp1.txt', 'public_html/temp2.txt'],
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        // Verify files were deleted
        expect(File::exists($this->testBasePath . '/example.com/public_html/temp1.txt'))->toBeFalse();
        expect(File::exists($this->testBasePath . '/example.com/public_html/temp2.txt'))->toBeFalse();
    });
});

describe('File Manager API - Compress', function () {
    test('user can compress files into archive', function () {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/domains/{$this->domain->id}/files/compress", [
                'paths' => ['public_html/index.html', 'public_html/style.css'],
                'archive_name' => 'backup.zip',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Archive created successfully.');

        // Verify archive was created
        $archivePath = $this->testBasePath . '/example.com/public_html/backup.zip';
        expect(File::exists($archivePath))->toBeTrue();
    });
});

describe('File Manager API - Extract', function () {
    test('user can extract archive', function () {
        // First create an archive
        $zip = new ZipArchive();
        $archivePath = $this->testBasePath . '/example.com/public_html/test.zip';
        $zip->open($archivePath, ZipArchive::CREATE);
        $zip->addFromString('extracted.txt', 'Extracted content');
        $zip->close();

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/domains/{$this->domain->id}/files/extract", [
                'path' => 'public_html/test.zip',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Archive extracted successfully.');

        // Verify file was extracted
        $extractedPath = $this->testBasePath . '/example.com/public_html/extracted.txt';
        expect(File::exists($extractedPath))->toBeTrue();
    });
});

describe('File Manager API - Permissions', function () {
    test('user can get file permissions', function () {
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/domains/{$this->domain->id}/files/permissions?path=public_html/index.html");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'path',
                    'numeric',
                    'readable',
                ],
            ]);
    });

    test('user can set file permissions', function () {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/domains/{$this->domain->id}/files/permissions", [
                'path' => 'public_html/index.html',
                'permissions' => '644',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Permissions updated successfully.');
    });

    test('invalid permissions format returns error', function () {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/domains/{$this->domain->id}/files/permissions", [
                'path' => 'public_html/index.html',
                'permissions' => 'invalid',
            ]);

        $response->assertStatus(422);
    });
});

describe('File Manager API - Search', function () {
    test('user can search files', function () {
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/domains/{$this->domain->id}/files/search?query=index");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'query',
                    'results',
                    'count',
                ],
            ]);

        expect($response->json('data.count'))->toBeGreaterThan(0);
    });

    test('search query must be at least 2 characters', function () {
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/domains/{$this->domain->id}/files/search?query=a");

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'QUERY_TOO_SHORT');
    });
});

describe('File Manager API - Disk Usage', function () {
    test('user can get disk usage', function () {
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/domains/{$this->domain->id}/files/disk-usage");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'used',
                    'formatted',
                ],
            ]);
    });
});

describe('File Manager API - Security', function () {
    test('cannot traverse outside domain directory', function () {
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/domains/{$this->domain->id}/files?path=../../etc");

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'LIST_FAILED');
    });

    test('cannot read file outside domain directory', function () {
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/domains/{$this->domain->id}/files/content?path=../other.com/public_html/index.html");

        $response->assertStatus(400);
    });
});
