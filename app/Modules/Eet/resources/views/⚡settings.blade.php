<?php

use App\Modules\Eet\Actions\UploadCertificate;
use App\Modules\Eet\Actions\InvalidCertificate;
use App\Modules\Eet\Actions\VerifyConnection;
use App\Modules\Eet\Enums\Environment;
use App\Modules\Eet\Models\Certificate;
use App\Modules\Eet\Models\Settings;
use App\Modules\Eet\Services\EetException;
use App\Modules\Eet\Services\IncompleteSetup;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Title('EET – nastavení')] class extends Component {
    use WithFileUploads;

    public string $taxpayer_eic = '';
    public string $premises_name = '';
    public string $premises_address = '';
    public string $unit_id = '';
    public string $register_id = '';
    public string $environment = '';
    public int $response_timeout = 2;

    /** @var TemporaryUploadedFile|null */
    public $certificateFile = null;
    public string $certificatePassword = '';

    /** @var array{success: bool, message: string, warnings: array<int, string>}|null */
    public ?array $verificationResult = null;

    /**
     * Naplní formulář uloženým nastavením, před prvním uložením výchozími hodnotami.
     */
    public function mount(): void
    {
        $settings = Settings::current() ?? new Settings;

        $this->taxpayer_eic = (string) $settings->taxpayer_eic;
        $this->premises_name = (string) $settings->premises_name;
        $this->premises_address = (string) $settings->premises_address;
        $this->unit_id = (string) $settings->unit_id;
        $this->register_id = (string) $settings->register_id;
        $this->environment = $settings->environment->value;
        $this->response_timeout = $settings->response_timeout;
    }

    #[Computed]
    public function activeCertificate(): ?Certificate
    {
        return Certificate::active();
    }

    /**
     * Uloží údaje poplatníka a pokladny.
     */
    public function saveSettings(): void
    {
        $this->authorize('eet.manage');

        $data = $this->validate([
            'taxpayer_eic' => ['required', 'string', 'regex:/^CZ[0-9]{8,10}$/'],
            'premises_name' => ['required', 'string', 'max:255'],
            'premises_address' => ['required', 'string', 'max:255'],
            'unit_id' => ['required', 'string', 'regex:/^[1-9][0-9]{0,8}$/'],
            'register_id' => ['required', 'string', 'regex:/^[0-9a-zA-Z\.,:;\/#\-_ ]{1,20}$/'],
            'environment' => ['required', Rule::enum(Environment::class)],
            'response_timeout' => ['required', 'integer', 'min:2', 'max:60'],
        ], [
            'taxpayer_eic.regex' => 'EIČ musí mít tvar CZ a 8 až 10 číslic.',
            'unit_id.regex' => 'Označení jednotky je číslo od 1 do 999999999.',
            'register_id.regex' => 'Označení pokladny může mít nejvýše 20 znaků: písmena bez diakritiky, číslice, mezera a znaky . , : ; / # - _',
        ], [
            'taxpayer_eic' => 'EIČ',
            'premises_name' => 'název provozovny',
            'premises_address' => 'adresa provozovny',
            'unit_id' => 'označení jednotky',
            'register_id' => 'označení pokladny',
            'environment' => 'prostředí',
            'response_timeout' => 'mezní doba odezvy',
        ]);

        (Settings::current() ?? new Settings)->fill($data)->save();

        Flux::toast(variant: 'success', text: 'Nastavení bylo uloženo.');
    }

    /**
     * Uloží nahraný soubor .p12 jako aktivní certifikát.
     */
    public function uploadCertificate(UploadCertificate $uploadCertificate): void
    {
        $this->authorize('eet.manage');

        $this->validate([
            'certificateFile' => ['required', 'file', 'extensions:p12,pfx', 'max:64'],
            'certificatePassword' => ['required', 'string'],
        ], attributes: [
            'certificateFile' => 'soubor certifikátu',
            'certificatePassword' => 'heslo',
        ]);

        try {
            $uploadCertificate->handle($this->certificateFile->getRealPath(), $this->certificatePassword);
        } catch (InvalidCertificate $exception) {
            throw ValidationException::withMessages(['certificateFile' => $exception->getMessage()]);
        } finally {
            $this->certificateFile->delete();
            $this->reset('certificateFile', 'certificatePassword');
        }

        unset($this->activeCertificate);

        Flux::toast(variant: 'success', text: 'Certifikát byl nahrán.');
    }

    /**
     * Odešle zprávu v ověřovacím režimu a zobrazí, co služba EET odpověděla.
     */
    public function verifyConnection(VerifyConnection $verifyConnection): void
    {
        $this->authorize('eet.manage');

        try {
            $response = $verifyConnection->handle();

            $this->verificationResult = [
                'success' => true,
                'message' => $response->isTest
                    ? 'Testovací prostředí EET zprávu přijalo.'
                    : 'Produkční prostředí EET zprávu přijalo.',
                'warnings' => $response->warnings,
            ];
        } catch (IncompleteSetup $exception) {
            $this->verificationResult = ['success' => false, 'message' => $exception->getMessage(), 'warnings' => []];
        } catch (EetException $exception) {
            $this->verificationResult = [
                'success' => false,
                'message' => $exception->errorCode === null
                    ? $exception->getMessage()
                    : "Chyba {$exception->errorCode}: {$exception->getMessage()}",
                'warnings' => [],
            ];
        }
    }
}; ?>

<section class="w-full">
    <flux:heading size="xl" level="1">EET – nastavení</flux:heading>
    <flux:subheading size="lg" class="mb-6">Údaje poplatníka, pokladní certifikát a ověření spojení</flux:subheading>
    <flux:separator variant="subtle" />

    <div class="mt-6 max-w-2xl space-y-10">
        <form wire:submit="saveSettings" class="space-y-6">
            <flux:heading size="lg" level="2">Údaje poplatníka</flux:heading>

            <flux:input wire:model="taxpayer_eic" label="EIČ" description="Evidenční identifikační číslo ve tvaru CZ a 8–10 číslic." required />

            <flux:input wire:model="premises_name" label="Název provozovny" required />

            <flux:input wire:model="premises_address" label="Adresa provozovny" required />

            <div class="grid gap-6 sm:grid-cols-2">
                <flux:input wire:model="unit_id" label="Označení jednotky" inputmode="numeric" required />

                <flux:input wire:model="register_id" label="Označení pokladny" required />
            </div>

            <div class="grid gap-6 sm:grid-cols-2">
                <flux:select wire:model="environment" label="Prostředí">
                    @foreach (Environment::cases() as $option)
                        <flux:select.option wire:key="environment-{{ $option->value }}" :value="$option->value">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="response_timeout" label="Mezní doba odezvy (s)" type="number" min="2" max="60" required />
            </div>

            <flux:button type="submit" variant="primary" data-test="save-settings">Uložit nastavení</flux:button>
        </form>

        <flux:separator variant="subtle" />

        <form wire:submit="uploadCertificate" class="space-y-6">
            <flux:heading size="lg" level="2">Pokladní certifikát</flux:heading>

            @if ($this->activeCertificate === null)
                <flux:callout variant="warning" icon="exclamation-triangle" heading="Zatím není nahrán žádný certifikát." />
            @else
                <flux:callout
                    :variant="$this->activeCertificate->isValid() ? 'success' : 'danger'"
                    :icon="$this->activeCertificate->isValid() ? 'check-circle' : 'x-circle'"
                >
                    <flux:callout.heading>
                        {{ $this->activeCertificate->isValid() ? 'Aktivní certifikát' : 'Aktivní certifikát není platný' }}:
                        {{ $this->activeCertificate->subject }}
                    </flux:callout.heading>
                    <flux:callout.text>
                        Platnost od {{ $this->activeCertificate->valid_from->format('j. n. Y') }}
                        do {{ $this->activeCertificate->valid_until->format('j. n. Y') }}.
                    </flux:callout.text>
                </flux:callout>
            @endif

            <flux:input wire:model="certificateFile" label="Soubor certifikátu (.p12)" type="file" accept=".p12,.pfx" />

            <flux:input wire:model="certificatePassword" label="Heslo k certifikátu" type="password" autocomplete="off" viewable />

            <flux:button type="submit" data-test="upload-certificate">Nahrát certifikát</flux:button>
        </form>

        <flux:separator variant="subtle" />

        <div class="space-y-6">
            <flux:heading size="lg" level="2">Ověření spojení</flux:heading>

            <flux:text>
                Odešle zkušební zprávu v ověřovacím režimu. Služba EET zkontroluje údaje i podpis, ale žádnou tržbu nezaeviduje.
            </flux:text>

            <flux:button wire:click="verifyConnection" icon="signal" data-test="verify-connection">Ověřit spojení</flux:button>

            @if ($verificationResult !== null)
                <flux:callout
                    :variant="$verificationResult['success'] ? 'success' : 'danger'"
                    :icon="$verificationResult['success'] ? 'check-circle' : 'x-circle'"
                >
                    <flux:callout.heading>{{ $verificationResult['success'] ? 'Spojení je v pořádku' : 'Ověření se nezdařilo' }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ $verificationResult['message'] }}

                        @foreach ($verificationResult['warnings'] as $code => $text)
                            <span wire:key="warning-{{ $code }}" class="block">Varování {{ $code }}: {{ $text }}</span>
                        @endforeach
                    </flux:callout.text>
                </flux:callout>
            @endif
        </div>
    </div>
</section>
