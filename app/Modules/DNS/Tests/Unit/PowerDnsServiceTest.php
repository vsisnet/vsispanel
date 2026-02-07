<?php

declare(strict_types=1);

use App\Modules\DNS\Services\PowerDnsService;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    config(['vsispanel.dns.powerdns_api_url' => 'http://127.0.0.1:8081']);
    config(['vsispanel.dns.powerdns_api_key' => 'testkey']);
    config(['vsispanel.dns.powerdns_server_id' => 'localhost']);
    config(['vsispanel.dns.primary_ns' => 'ns1.example.com']);
    config(['vsispanel.dns.admin_email' => 'admin@example.com']);
});

describe('PowerDnsService', function () {

    it('can be instantiated', function () {
        $service = new PowerDnsService();
        expect($service)->toBeInstanceOf(PowerDnsService::class);
    });

    describe('Record Validation', function () {

        it('validates A record with valid IPv4', function () {
            $service = new PowerDnsService();
            $result = $service->validateRecord('A', '@', '192.168.1.1');
            expect($result)->toBeTrue();
        });

        it('rejects A record with invalid IPv4', function () {
            $service = new PowerDnsService();
            expect(fn() => $service->validateRecord('A', '@', 'not-an-ip'))
                ->toThrow(RuntimeException::class, 'Invalid IPv4 address');
        });

        it('validates AAAA record with valid IPv6', function () {
            $service = new PowerDnsService();
            $result = $service->validateRecord('AAAA', '@', '2001:db8::1');
            expect($result)->toBeTrue();
        });

        it('rejects AAAA record with invalid IPv6', function () {
            $service = new PowerDnsService();
            expect(fn() => $service->validateRecord('AAAA', '@', '192.168.1.1'))
                ->toThrow(RuntimeException::class, 'Invalid IPv6 address');
        });

        it('validates CNAME record with valid hostname', function () {
            $service = new PowerDnsService();
            $result = $service->validateRecord('CNAME', 'www', 'example.com.');
            expect($result)->toBeTrue();
        });

        it('rejects CNAME record with invalid hostname', function () {
            $service = new PowerDnsService();
            expect(fn() => $service->validateRecord('CNAME', 'www', '-invalid'))
                ->toThrow(RuntimeException::class, 'Invalid hostname');
        });

        it('validates MX record with valid hostname', function () {
            $service = new PowerDnsService();
            $result = $service->validateRecord('MX', '@', 'mail.example.com.');
            expect($result)->toBeTrue();
        });

        it('validates TXT record with valid content', function () {
            $service = new PowerDnsService();
            $result = $service->validateRecord('TXT', '@', 'v=spf1 mx ~all');
            expect($result)->toBeTrue();
        });

        it('rejects TXT record exceeding max length', function () {
            $service = new PowerDnsService();
            $longContent = str_repeat('a', 5000);
            expect(fn() => $service->validateRecord('TXT', '@', $longContent))
                ->toThrow(RuntimeException::class, 'exceeds maximum length');
        });

        it('validates CAA record with valid format', function () {
            $service = new PowerDnsService();
            $result = $service->validateRecord('CAA', '@', '0 issue letsencrypt.org');
            expect($result)->toBeTrue();
        });

        it('rejects CAA record with invalid format', function () {
            $service = new PowerDnsService();
            expect(fn() => $service->validateRecord('CAA', '@', 'invalid'))
                ->toThrow(RuntimeException::class, 'Invalid CAA record format');
        });

        it('validates NS record with valid hostname', function () {
            $service = new PowerDnsService();
            $result = $service->validateRecord('NS', '@', 'ns1.example.com.');
            expect($result)->toBeTrue();
        });

        it('validates SRV record with valid hostname', function () {
            $service = new PowerDnsService();
            $result = $service->validateRecord('SRV', '_sip._tcp', 'sipserver.example.com.');
            expect($result)->toBeTrue();
        });

    });

    describe('DNS Templates', function () {

        it('returns available templates', function () {
            $service = new PowerDnsService();
            $templates = $service->getAvailableTemplates();

            expect($templates)->toBeArray();

            // Should have our created templates
            $templateNames = array_column($templates, 'name');
            expect($templateNames)->toContain('default');
            expect($templateNames)->toContain('google-workspace');
            expect($templateNames)->toContain('office365');
            expect($templateNames)->toContain('email-only');
        });

        it('template has required fields', function () {
            $service = new PowerDnsService();
            $templates = $service->getAvailableTemplates();

            foreach ($templates as $template) {
                expect($template)->toHaveKeys(['name', 'label', 'description', 'records_count']);
            }
        });

        it('default template has correct records', function () {
            $templatePath = resource_path('views/templates/dns/default.json');
            $content = json_decode(File::get($templatePath), true);

            expect($content)->toHaveKey('label');
            expect($content)->toHaveKey('description');
            expect($content)->toHaveKey('records');
            expect($content['records'])->toBeArray();
        });

        it('google workspace template has MX records', function () {
            $templatePath = resource_path('views/templates/dns/google-workspace.json');
            $content = json_decode(File::get($templatePath), true);

            $types = array_column($content['records'], 'type');
            expect($types)->toContain('MX');
            expect($types)->toContain('TXT');
        });

        it('office365 template has MX and autodiscover records', function () {
            $templatePath = resource_path('views/templates/dns/office365.json');
            $content = json_decode(File::get($templatePath), true);

            $types = array_column($content['records'], 'type');
            expect($types)->toContain('MX');
            expect($types)->toContain('CNAME');
            expect($types)->toContain('SRV');
        });

        it('email-only template has mail records', function () {
            $templatePath = resource_path('views/templates/dns/email-only.json');
            $content = json_decode(File::get($templatePath), true);

            $types = array_column($content['records'], 'type');
            expect($types)->toContain('MX');
            expect($types)->toContain('TXT');
            expect($types)->toContain('A');
        });

    });

    describe('Template Variables', function () {

        it('default template uses domain variable', function () {
            $templatePath = resource_path('views/templates/dns/default.json');
            $content = File::get($templatePath);

            expect($content)->toContain('{{domain}}');
        });

        it('email-only template uses server_ip variable', function () {
            $templatePath = resource_path('views/templates/dns/email-only.json');
            $content = File::get($templatePath);

            expect($content)->toContain('{{server_ip}}');
            expect($content)->toContain('{{domain}}');
        });

    });

});
