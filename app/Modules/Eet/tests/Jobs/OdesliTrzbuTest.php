<?php

namespace App\Modules\Eet\tests\Jobs;

use App\Modules\Eet\Actions\PosliTrzbu;
use App\Modules\Eet\Enums\StavTrzby;
use App\Modules\Eet\Jobs\OdesliTrzbu;
use App\Modules\Eet\Models\Trzba;
use App\Modules\Eet\Services\EetChyba;
use App\Modules\Eet\Services\FakeEetClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OdesliTrzbuTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_sale_finishes_the_job(): void
    {
        $trzba = Trzba::factory()->kOpakovani()->create();
        $job = (new OdesliTrzbu($trzba))->withFakeQueueInteractions();

        $job->handle(new PosliTrzbu(new FakeEetClient));

        $job->assertNotReleased();
        $this->assertSame(StavTrzby::Potvrzena, $trzba->refresh()->stav);
    }

    public function test_job_comes_back_in_a_minute_while_the_service_is_unavailable(): void
    {
        $trzba = Trzba::factory()->kOpakovani()->create();
        $client = (new FakeEetClient)->odpovi(new EetChyba('Spojení s EET selhalo', EetChyba::KOD_DOCASNA));
        $job = (new OdesliTrzbu($trzba))->withFakeQueueInteractions();

        $job->handle(new PosliTrzbu($client));

        $job->assertReleased(delay: 60);
        $this->assertSame(StavTrzby::KOpakovani, $trzba->refresh()->stav);
    }

    public function test_rejected_sale_is_not_retried(): void
    {
        $trzba = Trzba::factory()->kOpakovani()->create();
        $client = (new FakeEetClient)->odpovi(new EetChyba('Neplatny EIC', 6));
        $job = (new OdesliTrzbu($trzba))->withFakeQueueInteractions();

        $job->handle(new PosliTrzbu($client));

        $job->assertNotReleased();
        $this->assertSame(StavTrzby::Zamitnuta, $trzba->refresh()->stav);
    }

    public function test_job_keeps_trying_for_48_hours(): void
    {
        $this->freezeSecond();

        $job = new OdesliTrzbu(Trzba::factory()->kOpakovani()->create());

        $this->assertTrue(now()->addHours(48)->equalTo($job->retryUntil()));
    }
}
