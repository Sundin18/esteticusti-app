<?php

namespace App\Modules\Eet\tests\Actions;

use App\Modules\Eet\Actions\UploadCertificate;
use App\Modules\Eet\Actions\InvalidCertificate;
use App\Modules\Eet\database\factories\CertificateFactory;
use App\Modules\Eet\Models\Certificate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UploadCertificateTest extends TestCase
{
    use RefreshDatabase;

    private string $p12Path;

    protected function setUp(): void
    {
        parent::setUp();

        ['key' => $key, 'cert' => $certificate] = CertificateFactory::testKeyAndCertificate();
        openssl_pkcs12_export($certificate, $p12, $key, 'tajne-heslo');

        $this->p12Path = tempnam(sys_get_temp_dir(), 'eet-p12-');
        file_put_contents($this->p12Path, $p12);
    }

    protected function tearDown(): void
    {
        @unlink($this->p12Path);

        parent::tearDown();
    }

    public function test_stores_the_certificate_as_active_with_its_validity(): void
    {
        $this->freezeSecond();

        $certificate = (new UploadCertificate)->handle($this->p12Path, 'tajne-heslo');

        $this->assertTrue($certificate->is_active);
        $this->assertSame('CZ00000019', $certificate->subject);
        $this->assertTrue($certificate->isValid());
        $this->assertEqualsWithDelta(now()->addDays(365)->getTimestamp(), $certificate->valid_until->getTimestamp(), 5);
        $this->assertStringContainsString('BEGIN CERTIFICATE', $certificate->certificate);
        $this->assertStringContainsString('PRIVATE KEY', $certificate->private_key);
    }

    public function test_private_key_and_certificate_are_encrypted_in_the_database(): void
    {
        $certificate = (new UploadCertificate)->handle($this->p12Path, 'tajne-heslo');

        $row = DB::table('eet_certificates')->where('id', $certificate->id)->sole();

        $this->assertStringNotContainsString('PRIVATE KEY', $row->private_key);
        $this->assertStringNotContainsString('BEGIN CERTIFICATE', $row->certificate);
    }

    public function test_previous_certificate_is_kept_but_deactivated(): void
    {
        $previous = Certificate::factory()->create();

        $replacement = (new UploadCertificate)->handle($this->p12Path, 'tajne-heslo');

        $this->assertFalse($previous->refresh()->is_active);
        $this->assertTrue(Certificate::active()?->is($replacement));
    }

    public function test_wrong_password_is_rejected_and_keeps_the_current_certificate(): void
    {
        $previous = Certificate::factory()->create();

        try {
            (new UploadCertificate)->handle($this->p12Path, 'spatne-heslo');
            $this->fail('A wrong password should have thrown.');
        } catch (InvalidCertificate) {
            $this->assertTrue($previous->refresh()->is_active);
            $this->assertDatabaseCount('eet_certificates', 1);
        }
    }
}
