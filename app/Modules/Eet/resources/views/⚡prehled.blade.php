<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('EET – přehled')] class extends Component {
    //
}; ?>

<section class="w-full">
    <flux:heading size="xl" level="1">EET</flux:heading>
    <flux:subheading size="lg" class="mb-6">Evidence tržeb</flux:subheading>
    <flux:separator variant="subtle" />

    <flux:callout icon="information-circle" class="mt-6">
        <flux:callout.heading>Modul se připravuje</flux:callout.heading>
        <flux:callout.text>Přehled tržeb, stav spojení a platnost certifikátu přibudou v dalších fázích.</flux:callout.text>
    </flux:callout>
</section>
