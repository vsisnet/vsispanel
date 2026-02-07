<?php

declare(strict_types=1);

use App\Modules\DNS\Http\Controllers\DnsRecordController;
use App\Modules\DNS\Http\Controllers\DnsZoneController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', 'auth:sanctum'])->prefix('api/dns')->name('dns.')->group(function () {
    // DNS Templates
    Route::get('templates', [DnsZoneController::class, 'templates'])
        ->name('dns.templates');
    Route::post('templates/preview', [DnsZoneController::class, 'templatePreview'])
        ->name('dns.templates.preview');

    // DNS Zones
    Route::apiResource('zones', DnsZoneController::class)
        ->parameters(['zones' => 'zone'])
        ->names([
            'index' => 'dns.zones.index',
            'store' => 'dns.zones.store',
            'show' => 'dns.zones.show',
            'update' => 'dns.zones.update',
            'destroy' => 'dns.zones.destroy',
        ]);

    // Zone actions
    Route::post('zones/{zone}/apply-template', [DnsZoneController::class, 'applyTemplate'])
        ->name('dns.zones.apply-template');
    Route::get('zones/{zone}/export', [DnsZoneController::class, 'export'])
        ->name('dns.zones.export');

    // Bulk operations
    Route::post('zones/{zone}/bulk-add', [DnsZoneController::class, 'bulkAddRecords'])
        ->name('dns.zones.bulk-add');
    Route::post('zones/{zone}/bulk-delete', [DnsZoneController::class, 'bulkDeleteRecords'])
        ->name('dns.zones.bulk-delete');
    Route::post('zones/{zone}/bulk-update', [DnsZoneController::class, 'bulkUpdateRecords'])
        ->name('dns.zones.bulk-update');
    Route::post('zones/{zone}/import', [DnsZoneController::class, 'import'])
        ->name('dns.zones.import');
    Route::post('zones/{zone}/clone', [DnsZoneController::class, 'clone'])
        ->name('dns.zones.clone');
    Route::post('zones/{zone}/reset', [DnsZoneController::class, 'reset'])
        ->name('dns.zones.reset');

    // DNS Records (nested under zones)
    Route::get('zones/{zone}/records', [DnsRecordController::class, 'index'])
        ->name('dns.zones.records.index');
    Route::post('zones/{zone}/records', [DnsRecordController::class, 'store'])
        ->name('dns.zones.records.store');

    // DNS Records (direct access)
    Route::put('records/{record}', [DnsRecordController::class, 'update'])
        ->name('dns.records.update');
    Route::delete('records/{record}', [DnsRecordController::class, 'destroy'])
        ->name('dns.records.destroy');
    Route::put('records/{record}/toggle', [DnsRecordController::class, 'toggle'])
        ->name('dns.records.toggle');
});
