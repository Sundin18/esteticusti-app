<?php

namespace App\Modules\Eet\tests\Actions;

use App\Modules\Eet\Actions\NahrajCertifikat;
use App\Modules\Eet\Actions\NeplatnyCertifikat;
use App\Modules\Eet\database\factories\CertifikatFactory;
use App\Modules\Eet\Models\Certifikat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NahrajCertifikatTest extends TestCase
{
    use RefreshDatabase;

    private string $cestaP12;

    protected function setUp(): void
    {
        parent::setUp();

        ['key' => $klic, 'cert' => $certifikat] = CertifikatFactory::testovaciKlicACertifikat();
        openssl_pkcs12_export($certifikat, $p12, $klic, 'tajne-heslo');

        $this->cestaP12 = tempnam(sys_get_temp_dir(), 'eet-p12-');
        file_put_contents($this->cestaP12, $p12);
    }

    protected function tearDown(): void
    {
        @unlink($this->cestaP12);

        parent::tearDown();
    }

    public function test_stores_the_certificate_as_active_with_its_validity(): void
    {
        $this->freezeSecond();

        $certifikat = (new NahrajCertifikat)->handle($this->cestaP12, 'tajne-heslo');

        $this->assertTrue($certifikat->aktivni);
        $this->assertSame('CZ00000019', $certifikat->predmet);
        $this->assertTrue($certifikat->jePlatny());
        $this->assertEqualsWithDelta(now()->addDays(365)->getTimestamp(), $certifikat->platnost_do->getTimestamp(), 5);
        $this->assertStringContainsString('BEGIN CERTIFICATE', $certifikat->certifikat);
        $this->assertStringContainsString('PRIVATE KEY', $certifikat->privatni_klic);
    }

    public function test_private_key_and_certificate_are_encrypted_in_the_database(): void
    {
        $certifikat = (new NahrajCertifikat)->handle($this->cestaP12, 'tajne-heslo');

        $radek = DB::table('eet_certifikaty')->where('id', $certifikat->id)->sole();

        $this->assertStringNotContainsString('PRIVATE KEY', $radek->privatni_klic);
        $this->assertStringNotContainsString('BEGIN CERTIFICATE', $radek->certifikat);
    }

    public function test_previous_certificate_is_kept_but_deactivated(): void
    {
        $puvodni = Certifikat::factory()->create();

        $novy = (new NahrajCertifikat)->handle($this->cestaP12, 'tajne-heslo');

        $this->assertFalse($puvodni->refresh()->aktivni);
        $this->assertTrue(Certifikat::aktivni()?->is($novy));
    }

    public function test_wrong_password_is_rejected_and_keeps_the_current_certificate(): void
    {
        $puvodni = Certifikat::factory()->create();

        try {
            (new NahrajCertifikat)->handle($this->cestaP12, 'spatne-heslo');
            $this->fail('Špatné heslo mělo skončit výjimkou.');
        } catch (NeplatnyCertifikat) {
            $this->assertTrue($puvodni->refresh()->aktivni);
            $this->assertDatabaseCount('eet_certifikaty', 1);
        }
    }
}
