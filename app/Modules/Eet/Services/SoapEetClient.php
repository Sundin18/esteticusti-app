<?php

namespace App\Modules\Eet\Services;

use App\Modules\Eet\Models\Certificate;
use App\Modules\Eet\Models\Settings;
use Eet2\Client;
use Eet2\EetException as LibraryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Sestavuje, podepisuje a ověřuje zprávy knihovnou jakubsilny/eet2 a odesílá je HTTP klientem Laravelu.
 */
class SoapEetClient implements EetClient
{
    public function send(SaleData $sale, bool $firstAttempt = true, bool $verifyOnly = false): EetResponse
    {
        $settings = Settings::current() ?? throw IncompleteSetup::missingSettings();
        $certificate = Certificate::active() ?? throw IncompleteSetup::missingCertificate();

        $messageUuid = (string) Str::uuid();
        $requestXml = $this->buildMessage($settings, $certificate, $sale, $messageUuid, $firstAttempt, $verifyOnly);

        try {
            $httpResponse = Http::timeout($settings->response_timeout)
                ->withHeaders(['SOAPAction' => '"http://fs.gov.cz/eet/OdeslaniTrzby"'])
                ->withBody($requestXml, 'text/xml; charset=utf-8')
                ->post($settings->environment->url());
        } catch (ConnectionException $exception) {
            throw new EetException(
                'Spojení s EET selhalo: '.$exception->getMessage(),
                EetException::CODE_TEMPORARY,
                $requestXml,
                messageUuid: $messageUuid,
                previous: $exception,
            );
        }

        $responseXml = $httpResponse->body();
        $transactionId = $httpResponse->header('X-Global-Transaction-Id') ?: null;

        try {
            $result = Client::parseResponse($responseXml, $httpResponse->status(), $messageUuid, $transactionId, $verifyOnly);

            if ($result['pok'] !== null) {
                Client::verifyResponseSignature($responseXml);
            }
        } catch (LibraryException $exception) {
            throw new EetException($exception->getMessage(), $exception->getCode(), $requestXml, $responseXml, $messageUuid, $exception);
        }

        return new EetResponse(
            messageUuid: $messageUuid,
            pok: $result['pok'],
            isTest: $result['test'],
            warnings: $result['warnings'],
            requestXml: $requestXml,
            responseXml: $responseXml,
            transactionId: $transactionId,
        );
    }

    /**
     * Sestaví podepsanou zprávu SOAP; chyby v údajích nebo certifikátu se projeví dřív, než se cokoli odešle.
     *
     * Klíče pole jsou názvy atributů rozhraní EET.
     */
    protected function buildMessage(
        Settings $settings,
        Certificate $certificate,
        SaleData $sale,
        string $messageUuid,
        bool $firstAttempt,
        bool $verifyOnly,
    ): string {
        try {
            return new Client($certificate->private_key, $certificate->certificate)->buildSignedMessage([
                'eic_popl' => $settings->taxpayer_eic,
                'id_jednotky' => $settings->unit_id,
                'id_pokl' => $settings->register_id,
                'porad_cis' => $sale->receiptNumber,
                'dat_trzby' => $sale->soldAt,
                'celk_trzba' => $sale->totalAmount,
            ], $messageUuid, $firstAttempt, $verifyOnly);
        } catch (LibraryException $exception) {
            throw new EetException($exception->getMessage(), previous: $exception);
        }
    }
}
