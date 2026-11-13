<?php

use App\Modules\Eet\Actions\NahrajCertifikat;
use App\Modules\Eet\Actions\NeplatnyCertifikat;
use App\Modules\Eet\Actions\OverSpojeni;
use App\Modules\Eet\Enums\Prostredi;
use App\Modules\Eet\Models\Certifikat;
use App\Modules\Eet\Models\Nastaveni;
use App\Modules\Eet\Services\EetChyba;
use App\Modules\Eet\Services\NeuplneNastaveni;
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

    public string $eic_popl = '';
    public string $nazev_provozovny = '';
    public string $adresa_provozovny = '';
    public string $id_jednotky = '';
    public string $id_pokl = '';
    public string $prostredi = '';
    public int $mezni_doba_odezvy = 2;

    /** @var TemporaryUploadedFile|null */
    public $souborCertifikatu = null;
    public string $hesloCertifikatu = '';

    /** @var array{uspech: bool, zprava: string, varovani: array<int, string>}|null */
    public ?array $vysledekOvereni = null;

    /**
     * Fill the form with the saved settings, or with the defaults before the first save.
     */
    public function mount(): void
    {
        $nastaveni = Nastaveni::aktualni() ?? new Nastaveni;

        $this->eic_popl = (string) $nastaveni->eic_popl;
        $this->nazev_provozovny = (string) $nastaveni->nazev_provozovny;
        $this->adresa_provozovny = (string) $nastaveni->adresa_provozovny;
        $this->id_jednotky = (string) $nastaveni->id_jednotky;
        $this->id_pokl = (string) $nastaveni->id_pokl;
        $this->prostredi = $nastaveni->prostredi->value;
        $this->mezni_doba_odezvy = $nastaveni->mezni_doba_odezvy;
    }

    #[Computed]
    public function aktivniCertifikat(): ?Certifikat
    {
        return Certifikat::aktivni();
    }

    /**
     * Save the taxpayer and cash register details.
     */
    public function ulozNastaveni(): void
    {
        $this->authorize('eet.manage');

        $udaje = $this->validate([
            'eic_popl' => ['required', 'string', 'regex:/^CZ[0-9]{8,10}$/'],
            'nazev_provozovny' => ['required', 'string', 'max:255'],
            'adresa_provozovny' => ['required', 'string', 'max:255'],
            'id_jednotky' => ['required', 'string', 'regex:/^[1-9][0-9]{0,8}$/'],
            'id_pokl' => ['required', 'string', 'regex:/^[0-9a-zA-Z\.,:;\/#\-_ ]{1,20}$/'],
            'prostredi' => ['required', Rule::enum(Prostredi::class)],
            'mezni_doba_odezvy' => ['required', 'integer', 'min:2', 'max:60'],
        ], [
            'eic_popl.regex' => 'EIČ musí mít tvar CZ a 8 až 10 číslic.',
            'id_jednotky.regex' => 'Označení jednotky je číslo od 1 do 999999999.',
            'id_pokl.regex' => 'Označení pokladny může mít nejvýše 20 znaků: písmena bez diakritiky, číslice, mezera a znaky . , : ; / # - _',
        ], [
            'eic_popl' => 'EIČ',
            'nazev_provozovny' => 'název provozovny',
            'adresa_provozovny' => 'adresa provozovny',
            'id_jednotky' => 'označení jednotky',
            'id_pokl' => 'označení pokladny',
            'prostredi' => 'prostředí',
            'mezni_doba_odezvy' => 'mezní doba odezvy',
        ]);

        (Nastaveni::aktualni() ?? new Nastaveni)->fill($udaje)->save();

        Flux::toast(variant: 'success', text: 'Nastavení bylo uloženo.');
    }

    /**
     * Store the uploaded .p12 file as the active certificate.
     */
    public function nahrajCertifikat(NahrajCertifikat $nahrajCertifikat): void
    {
        $this->authorize('eet.manage');

        $this->validate([
            'souborCertifikatu' => ['required', 'file', 'extensions:p12,pfx', 'max:64'],
            'hesloCertifikatu' => ['required', 'string'],
        ], attributes: [
            'souborCertifikatu' => 'soubor certifikátu',
            'hesloCertifikatu' => 'heslo',
        ]);

        try {
            $nahrajCertifikat->handle($this->souborCertifikatu->getRealPath(), $this->hesloCertifikatu);
        } catch (NeplatnyCertifikat $vyjimka) {
            throw ValidationException::withMessages(['souborCertifikatu' => $vyjimka->getMessage()]);
        } finally {
            $this->souborCertifikatu->delete();
            $this->reset('souborCertifikatu', 'hesloCertifikatu');
        }

        unset($this->aktivniCertifikat);

        Flux::toast(variant: 'success', text: 'Certifikát byl nahrán.');
    }

    /**
     * Send a message in verification mode and show what the EET service answered.
     */
    public function overSpojeni(OverSpojeni $overSpojeni): void
    {
        $this->authorize('eet.manage');

        try {
            $odpoved = $overSpojeni->handle();

            $this->vysledekOvereni = [
                'uspech' => true,
                'zprava' => $odpoved->test
                    ? 'Testovací prostředí EET zprávu přijalo.'
                    : 'Produkční prostředí EET zprávu přijalo.',
                'varovani' => $odpoved->varovani,
            ];
        } catch (NeuplneNastaveni $vyjimka) {
            $this->vysledekOvereni = ['uspech' => false, 'zprava' => $vyjimka->getMessage(), 'varovani' => []];
        } catch (EetChyba $vyjimka) {
            $this->vysledekOvereni = [
                'uspech' => false,
                'zprava' => $vyjimka->kod === null
                    ? $vyjimka->getMessage()
                    : "Chyba {$vyjimka->kod}: {$vyjimka->getMessage()}",
                'varovani' => [],
            ];
        }
    }
}; ?>

<section class="w-full">
    <flux:heading size="xl" level="1">EET – nastavení</flux:heading>
    <flux:subheading size="lg" class="mb-6">Údaje poplatníka, pokladní certifikát a ověření spojení</flux:subheading>
    <flux:separator variant="subtle" />

    <div class="mt-6 max-w-2xl space-y-10">
        <form wire:submit="ulozNastaveni" class="space-y-6">
            <flux:heading size="lg" level="2">Údaje poplatníka</flux:heading>

            <flux:input wire:model="eic_popl" label="EIČ" description="Evidenční identifikační číslo ve tvaru CZ a 8–10 číslic." required />

            <flux:input wire:model="nazev_provozovny" label="Název provozovny" required />

            <flux:input wire:model="adresa_provozovny" label="Adresa provozovny" required />

            <div class="grid gap-6 sm:grid-cols-2">
                <flux:input wire:model="id_jednotky" label="Označení jednotky" inputmode="numeric" required />

                <flux:input wire:model="id_pokl" label="Označení pokladny" required />
            </div>

            <div class="grid gap-6 sm:grid-cols-2">
                <flux:select wire:model="prostredi" label="Prostředí">
                    @foreach (Prostredi::cases() as $moznost)
                        <flux:select.option wire:key="prostredi-{{ $moznost->value }}" :value="$moznost->value">{{ $moznost->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="mezni_doba_odezvy" label="Mezní doba odezvy (s)" type="number" min="2" max="60" required />
            </div>

            <flux:button type="submit" variant="primary" data-test="ulozit-nastaveni">Uložit nastavení</flux:button>
        </form>

        <flux:separator variant="subtle" />

        <form wire:submit="nahrajCertifikat" class="space-y-6">
            <flux:heading size="lg" level="2">Pokladní certifikát</flux:heading>

            @if ($this->aktivniCertifikat === null)
                <flux:callout variant="warning" icon="exclamation-triangle" heading="Zatím není nahrán žádný certifikát." />
            @else
                <flux:callout
                    :variant="$this->aktivniCertifikat->jePlatny() ? 'success' : 'danger'"
                    :icon="$this->aktivniCertifikat->jePlatny() ? 'check-circle' : 'x-circle'"
                >
                    <flux:callout.heading>
                        {{ $this->aktivniCertifikat->jePlatny() ? 'Aktivní certifikát' : 'Aktivní certifikát není platný' }}:
                        {{ $this->aktivniCertifikat->predmet }}
                    </flux:callout.heading>
                    <flux:callout.text>
                        Platnost od {{ $this->aktivniCertifikat->platnost_od->format('j. n. Y') }}
                        do {{ $this->aktivniCertifikat->platnost_do->format('j. n. Y') }}.
                    </flux:callout.text>
                </flux:callout>
            @endif

            <flux:input wire:model="souborCertifikatu" label="Soubor certifikátu (.p12)" type="file" accept=".p12,.pfx" />

            <flux:input wire:model="hesloCertifikatu" label="Heslo k certifikátu" type="password" autocomplete="off" viewable />

            <flux:button type="submit" data-test="nahrat-certifikat">Nahrát certifikát</flux:button>
        </form>

        <flux:separator variant="subtle" />

        <div class="space-y-6">
            <flux:heading size="lg" level="2">Ověření spojení</flux:heading>

            <flux:text>
                Odešle zkušební zprávu v ověřovacím režimu. Služba EET zkontroluje údaje i podpis, ale žádnou tržbu nezaeviduje.
            </flux:text>

            <flux:button wire:click="overSpojeni" icon="signal" data-test="overit-spojeni">Ověřit spojení</flux:button>

            @if ($vysledekOvereni !== null)
                <flux:callout
                    :variant="$vysledekOvereni['uspech'] ? 'success' : 'danger'"
                    :icon="$vysledekOvereni['uspech'] ? 'check-circle' : 'x-circle'"
                >
                    <flux:callout.heading>{{ $vysledekOvereni['uspech'] ? 'Spojení je v pořádku' : 'Ověření se nezdařilo' }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ $vysledekOvereni['zprava'] }}

                        @foreach ($vysledekOvereni['varovani'] as $kod => $text)
                            <span wire:key="varovani-{{ $kod }}" class="block">Varování {{ $kod }}: {{ $text }}</span>
                        @endforeach
                    </flux:callout.text>
                </flux:callout>
            @endif
        </div>
    </div>
</section>
