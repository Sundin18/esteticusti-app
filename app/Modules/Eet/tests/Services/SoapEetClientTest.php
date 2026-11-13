<?php

namespace App\Modules\Eet\tests\Services;

use App\Modules\Eet\Models\Certificate;
use App\Modules\Eet\Models\Settings;
use App\Modules\Eet\Services\EetException;
use App\Modules\Eet\Services\IncompleteSetup;
use App\Modules\Eet\Services\SaleData;
use App\Modules\Eet\Services\SoapEetClient;
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

    public function test_verification_message_is_valid_against_the_official_schema_and_carries_the_taxpayer_of_the_sale(): void
    {
        $settings = Settings::factory()->create(['taxpayer_eic' => 'CZ00000019', 'unit_id' => 7, 'register_id' => 'JINA']);
        Certificate::factory()->create();
        Http::fake(['*' => Http::response($this->errorResponse(0, 'Zprava v overovacim modu zpracovana'))]);

        $response = (new SoapEetClient)->send($this->saleData(), verifyOnly: true);

        $this->assertNull($response->pok);
        $this->assertTrue($response->isTest);

        Http::assertSent(function (Request $request) use ($settings, $response): bool {
            $saleElement = $this->saleElement($request->body());

            $standaloneSale = new DOMDocument;
            $standaloneSale->appendChild($standaloneSale->importNode($saleElement, true));
            $this->assertTrue($standaloneSale->schemaValidate(__DIR__.'/../fixtures/EETXMLSchema.xsd'));

            $header = $this->childElement($saleElement, 'Hlavicka');
            $data = $this->childElement($saleElement, 'Data');

            $this->assertSame($settings->environment->url(), $request->url());
            $this->assertSame('text/xml; charset=utf-8', $request->header('Content-Type')[0]);
            $this->assertSame($response->messageUuid, $header->getAttribute('uuid_zpravy'));
            $this->assertSame('true', $header->getAttribute('overeni'));
            $this->assertSame('true', $header->getAttribute('prvni_zaslani'));
            $this->assertSame('CZ683555118', $data->getAttribute('eic_popl'));
            $this->assertSame('101', $data->getAttribute('id_jednotky'));
            $this->assertSame('P1', $data->getAttribute('id_pokl'));
            $this->assertSame('R/1', $data->getAttribute('porad_cis'));
            $this->assertSame('2027-01-09T04:25:28+01:00', $data->getAttribute('dat_trzby'));
            $this->assertSame('1500.00', $data->getAttribute('celk_trzba'));
            $this->assertSame($request->body(), $response->requestXml);

            return true;
        });
    }

    public function test_repeated_sending_is_marked_in_the_message_and_omits_the_verification_flag(): void
    {
        Settings::factory()->create();
        Certificate::factory()->create();
        Http::fake(['*' => Http::response($this->errorResponse(6, 'Neplatny EIC'))]);

        try {
            (new SoapEetClient)->send($this->saleData(), firstAttempt: false);
            $this->fail('A rejected message should have thrown.');
        } catch (EetException $exception) {
            $header = $this->childElement($this->saleElement((string) $exception->requestXml), 'Hlavicka');

            $this->assertSame('false', $header->getAttribute('prvni_zaslani'));
            $this->assertFalse($header->hasAttribute('overeni'));
        }
    }

    public function test_rejection_by_the_service_is_a_permanent_error_with_its_code(): void
    {
        Settings::factory()->create();
        Certificate::factory()->create();
        Http::fake(['*' => Http::response($this->errorResponse(6, 'Neplatny EIC'))]);

        try {
            (new SoapEetClient)->send($this->saleData());
            $this->fail('A rejected message should have thrown.');
        } catch (EetException $exception) {
            $this->assertSame(6, $exception->errorCode);
            $this->assertFalse($exception->isTemporary());
            $this->assertStringContainsString('Neplatny EIC', $exception->getMessage());
            $this->assertStringContainsString('Neplatny EIC', (string) $exception->responseXml);
        }
    }

    public function test_processing_error_code_8_is_permanent(): void
    {
        Settings::factory()->create();
        Certificate::factory()->create();
        Http::fake(['*' => Http::response($this->errorResponse(8, 'Chyba pri zpracovani'))]);

        try {
            (new SoapEetClient)->send($this->saleData());
            $this->fail('A rejected message should have thrown.');
        } catch (EetException $exception) {
            $this->assertFalse($exception->isTemporary());
        }
    }

    public function test_unreachable_service_is_a_temporary_error(): void
    {
        Settings::factory()->create();
        Certificate::factory()->create();
        Http::fake(fn () => throw new ConnectionException('Operation timed out'));

        try {
            (new SoapEetClient)->send($this->saleData());
            $this->fail('An unreachable service should have thrown.');
        } catch (EetException $exception) {
            $this->assertSame(EetException::CODE_TEMPORARY, $exception->errorCode);
            $this->assertTrue($exception->isTemporary());
            $this->assertNotNull($exception->requestXml);
        }
    }

    public function test_unreadable_response_is_a_temporary_error(): void
    {
        Settings::factory()->create();
        Certificate::factory()->create();
        Http::fake(['*' => Http::response('Service Unavailable', 503)]);

        try {
            (new SoapEetClient)->send($this->saleData());
            $this->fail('An unreadable response should have thrown.');
        } catch (EetException $exception) {
            $this->assertTrue($exception->isTemporary());
        }
    }

    public function test_confirmation_without_a_valid_signature_is_not_trusted(): void
    {
        Settings::factory()->create();
        Certificate::factory()->create();
        Http::fake(['*' => Http::response($this->envelope(
            '<eet:Potvrzeni pok="987a6be5-6af5-44f3-b4fc-987654321000-ff" test="true"/>',
        ))]);

        try {
            (new SoapEetClient)->send($this->saleData());
            $this->fail('An unsigned confirmation should have thrown.');
        } catch (EetException $exception) {
            $this->assertSame(EetException::CODE_INVALID_SIGNATURE, $exception->errorCode);
            $this->assertTrue($exception->isTemporary());
        }
    }

    public function test_invalid_sale_data_fails_before_anything_is_sent(): void
    {
        Settings::factory()->create();
        Certificate::factory()->create();
        Http::fake();

        try {
            (new SoapEetClient)->send(new SaleData('CZ683555118', 101, 'P1', 'číslo s diakritikou', now(), '1.00'));
            $this->fail('Invalid sale data should have thrown.');
        } catch (EetException $exception) {
            $this->assertNull($exception->errorCode);
            $this->assertFalse($exception->isTemporary());
        }

        Http::assertNothingSent();
    }

    public function test_missing_settings_are_reported(): void
    {
        Certificate::factory()->create();

        $this->expectExceptionObject(IncompleteSetup::missingSettings());

        (new SoapEetClient)->send($this->saleData());
    }

    public function test_missing_active_certificate_is_reported(): void
    {
        Settings::factory()->create();
        Certificate::factory()->inactive()->create();

        $this->expectExceptionObject(IncompleteSetup::missingCertificate());

        (new SoapEetClient)->send($this->saleData());
    }

    private function saleData(): SaleData
    {
        return new SaleData('CZ683555118', 101, 'P1', 'R/1', Carbon::parse('2027-01-09 04:25:28', 'Europe/Prague'), '1500.00');
    }

    private function errorResponse(int $code, string $text): string
    {
        return $this->envelope("<eet:Chyba kod=\"{$code}\" test=\"true\">{$text}</eet:Chyba>");
    }

    private function envelope(string $responseContent): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:eet="'.self::NS_EET.'">'
            .'<soapenv:Header/><soapenv:Body><eet:Odpoved>'
            .'<eet:Hlavicka dat_odmit="2027-01-09T04:25:29+01:00"/>'
            .$responseContent
            .'</eet:Odpoved></soapenv:Body></soapenv:Envelope>';
    }

    private function saleElement(string $xml): DOMElement
    {
        $document = new DOMDocument;
        $document->loadXML($xml);

        $saleElement = $document->getElementsByTagNameNS(self::NS_EET, 'Trzba')->item(0);
        $this->assertInstanceOf(DOMElement::class, $saleElement);

        return $saleElement;
    }

    private function childElement(DOMElement $parent, string $name): DOMElement
    {
        $child = $parent->getElementsByTagNameNS(self::NS_EET, $name)->item(0);
        $this->assertInstanceOf(DOMElement::class, $child);

        return $child;
    }
}
