<?php

namespace App\Modules\Eet\tests\Services;

use App\Modules\Eet\Models\Certifikat;
use App\Modules\Eet\Models\Nastaveni;
use App\Modules\Eet\Services\EetChyba;
use App\Modules\Eet\Services\NeuplneNastaveni;
use App\Modules\Eet\Services\SoapEetClient;
use App\Modules\Eet\Services\UdajeTrzby;
use DOMDocument;
use DOMElement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SoapEetClientTest extends TestCase
{
    use RefreshDatabase;

    private const string NS_EET = 'http://fs.gov.cz/eet/schema/v4';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_verification_message_is_valid_against_the_official_schema_and_accepted(): void
    {
        $nastaveni = Nastaveni::factory()->create(['eic_popl' => 'CZ683555118', 'id_jednotky' => 101, 'id_pokl' => 'P1']);
        Certifikat::factory()->create();
        Http::fake(['*' => Http::response($this->odpovedSChybou(0, 'Zprava v overovacim modu zpracovana'))]);

        $odpoved = (new SoapEetClient)->odesli($this->trzba(), overeni: true);

        $this->assertNull($odpoved->pok);
        $this->assertTrue($odpoved->test);

        Http::assertSent(function (Request $request) use ($nastaveni, $odpoved): bool {
            $trzba = $this->trzbaZeZpravy($request->body());

            $samostatnaTrzba = new DOMDocument;
            $samostatnaTrzba->appendChild($samostatnaTrzba->importNode($trzba, true));
            $this->assertTrue($samostatnaTrzba->schemaValidate(__DIR__.'/../fixtures/EETXMLSchema.xsd'));

            $hlavicka = $this->potomek($trzba, 'Hlavicka');
            $data = $this->potomek($trzba, 'Data');

            $this->assertSame($nastaveni->prostredi->url(), $request->url());
            $this->assertSame('text/xml; charset=utf-8', $request->header('Content-Type')[0]);
            $this->assertSame($odpoved->uuidZpravy, $hlavicka->getAttribute('uuid_zpravy'));
            $this->assertSame('true', $hlavicka->getAttribute('overeni'));
            $this->assertSame('true', $hlavicka->getAttribute('prvni_zaslani'));
            $this->assertSame('CZ683555118', $data->getAttribute('eic_popl'));
            $this->assertSame('101', $data->getAttribute('id_jednotky'));
            $this->assertSame('P1', $data->getAttribute('id_pokl'));
            $this->assertSame('R/1', $data->getAttribute('porad_cis'));
            $this->assertSame('2027-01-09T04:25:28+01:00', $data->getAttribute('dat_trzby'));
            $this->assertSame('1500.00', $data->getAttribute('celk_trzba'));
            $this->assertSame($request->body(), $odpoved->xmlPozadavku);

            return true;
        });
    }

    public function test_repeated_sending_is_marked_in_the_message_and_omits_the_verification_flag(): void
    {
        Nastaveni::factory()->create();
        Certifikat::factory()->create();
        Http::fake(['*' => Http::response($this->odpovedSChybou(6, 'Neplatny EIC'))]);

        try {
            (new SoapEetClient)->odesli($this->trzba(), prvniZaslani: false);
            $this->fail('Odmítnutá zpráva měla skončit výjimkou.');
        } catch (EetChyba $chyba) {
            $hlavicka = $this->potomek($this->trzbaZeZpravy((string) $chyba->xmlPozadavku), 'Hlavicka');

            $this->assertSame('false', $hlavicka->getAttribute('prvni_zaslani'));
            $this->assertFalse($hlavicka->hasAttribute('overeni'));
        }
    }

    public function test_rejection_by_the_service_is_a_permanent_error_with_its_code(): void
    {
        Nastaveni::factory()->create();
        Certifikat::factory()->create();
        Http::fake(['*' => Http::response($this->odpovedSChybou(6, 'Neplatny EIC'))]);

        try {
            (new SoapEetClient)->odesli($this->trzba());
            $this->fail('Odmítnutá zpráva měla skončit výjimkou.');
        } catch (EetChyba $chyba) {
            $this->assertSame(6, $chyba->kod);
            $this->assertFalse($chyba->jeDocasna());
            $this->assertStringContainsString('Neplatny EIC', $chyba->getMessage());
            $this->assertStringContainsString('Neplatny EIC', (string) $chyba->xmlOdpovedi);
        }
    }

    public function test_processing_error_code_8_is_permanent(): void
    {
        Nastaveni::factory()->create();
        Certifikat::factory()->create();
        Http::fake(['*' => Http::response($this->odpovedSChybou(8, 'Chyba pri zpracovani'))]);

        try {
            (new SoapEetClient)->odesli($this->trzba());
            $this->fail('Odmítnutá zpráva měla skončit výjimkou.');
        } catch (EetChyba $chyba) {
            $this->assertFalse($chyba->jeDocasna());
        }
    }

    public function test_unreachable_service_is_a_temporary_error(): void
    {
        Nastaveni::factory()->create();
        Certifikat::factory()->create();
        Http::fake(fn () => throw new ConnectionException('Operation timed out'));

        try {
            (new SoapEetClient)->odesli($this->trzba());
            $this->fail('Nedostupná služba měla skončit výjimkou.');
        } catch (EetChyba $chyba) {
            $this->assertSame(EetChyba::KOD_DOCASNA, $chyba->kod);
            $this->assertTrue($chyba->jeDocasna());
            $this->assertNotNull($chyba->xmlPozadavku);
        }
    }

    public function test_unreadable_response_is_a_temporary_error(): void
    {
        Nastaveni::factory()->create();
        Certifikat::factory()->create();
        Http::fake(['*' => Http::response('Service Unavailable', 503)]);

        try {
            (new SoapEetClient)->odesli($this->trzba());
            $this->fail('Nečitelná odpověď měla skončit výjimkou.');
        } catch (EetChyba $chyba) {
            $this->assertTrue($chyba->jeDocasna());
        }
    }

    public function test_confirmation_without_a_valid_signature_is_not_trusted(): void
    {
        Nastaveni::factory()->create();
        Certifikat::factory()->create();
        Http::fake(['*' => Http::response($this->obalka(
            '<eet:Potvrzeni pok="987a6be5-6af5-44f3-b4fc-987654321000-ff" test="true"/>',
        ))]);

        try {
            (new SoapEetClient)->odesli($this->trzba());
            $this->fail('Nepodepsané potvrzení mělo skončit výjimkou.');
        } catch (EetChyba $chyba) {
            $this->assertSame(EetChyba::KOD_NEPLATNY_PODPIS, $chyba->kod);
            $this->assertTrue($chyba->jeDocasna());
        }
    }

    public function test_invalid_sale_data_fails_before_anything_is_sent(): void
    {
        Nastaveni::factory()->create();
        Certifikat::factory()->create();
        Http::fake();

        try {
            (new SoapEetClient)->odesli(new UdajeTrzby('číslo s diakritikou', now(), '1.00'));
            $this->fail('Neplatné údaje měly skončit výjimkou.');
        } catch (EetChyba $chyba) {
            $this->assertNull($chyba->kod);
            $this->assertFalse($chyba->jeDocasna());
        }

        Http::assertNothingSent();
    }

    public function test_missing_settings_are_reported(): void
    {
        Certifikat::factory()->create();

        $this->expectExceptionObject(NeuplneNastaveni::chybiNastaveni());

        (new SoapEetClient)->odesli($this->trzba());
    }

    public function test_missing_active_certificate_is_reported(): void
    {
        Nastaveni::factory()->create();
        Certifikat::factory()->neaktivni()->create();

        $this->expectExceptionObject(NeuplneNastaveni::chybiCertifikat());

        (new SoapEetClient)->odesli($this->trzba());
    }

    private function trzba(): UdajeTrzby
    {
        return new UdajeTrzby('R/1', Carbon::parse('2027-01-09 04:25:28', 'Europe/Prague'), '1500.00');
    }

    private function odpovedSChybou(int $kod, string $text): string
    {
        return $this->obalka("<eet:Chyba kod=\"{$kod}\" test=\"true\">{$text}</eet:Chyba>");
    }

    private function obalka(string $obsahOdpovedi): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:eet="'.self::NS_EET.'">'
            .'<soapenv:Header/><soapenv:Body><eet:Odpoved>'
            .'<eet:Hlavicka dat_odmit="2027-01-09T04:25:29+01:00"/>'
            .$obsahOdpovedi
            .'</eet:Odpoved></soapenv:Body></soapenv:Envelope>';
    }

    private function trzbaZeZpravy(string $xml): DOMElement
    {
        $zprava = new DOMDocument;
        $zprava->loadXML($xml);

        $trzba = $zprava->getElementsByTagNameNS(self::NS_EET, 'Trzba')->item(0);
        $this->assertInstanceOf(DOMElement::class, $trzba);

        return $trzba;
    }

    private function potomek(DOMElement $rodic, string $nazev): DOMElement
    {
        $potomek = $rodic->getElementsByTagNameNS(self::NS_EET, $nazev)->item(0);
        $this->assertInstanceOf(DOMElement::class, $potomek);

        return $potomek;
    }
}
