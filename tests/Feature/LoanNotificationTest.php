<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Models\G001M001Unit;
use App\Models\G002M007Item;
use App\Models\G002M003ItemManagement;
use App\Models\User;
use App\Services\LoanRequestService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_submission_creates_actionable_database_notifications_for_requester_and_reviewers(): void
    {
        [$requester, $reviewer, $item] = $this->fixtures();

        app(LoanRequestService::class)->submit($requester, $this->requestData($item));

        $requesterNotification = $requester->notifications()->first();
        $reviewerNotification = $reviewer->notifications()->first();

        $this->assertNotNull($requesterNotification);
        $this->assertSame('Pengajuan berhasil dikirim', $requesterNotification->data['title']);
        $this->assertTrue($requesterNotification->data['actions'][0]['shouldMarkAsRead']);
        $this->assertStringContainsString('/admin/activity/', $requesterNotification->data['actions'][0]['url']);

        $this->assertNotNull($reviewerNotification);
        $this->assertSame('Pengajuan baru perlu ditinjau', $reviewerNotification->data['title']);
        $this->assertSame('warning', $reviewerNotification->data['status']);
    }

    public function test_requester_is_notified_when_aggregate_loan_status_changes(): void
    {
        [$requester, $reviewer, $item] = $this->fixtures();

        $activity = app(LoanRequestService::class)->submit($requester, $this->requestData($item));
        $this->actingAs($reviewer);
        $activity->item_reservation()->first()->update(['status' => ReservationStatus::Approved->value]);

        $titles = $requester->notifications()->get()->pluck('data.title');

        $this->assertContains('Pengajuan disetujui', $titles);
        $this->assertSame(ReservationStatus::Approved->value, $activity->fresh()->status);
    }

    public function test_cancellation_notifies_requester_and_clears_the_reviewers_pending_context(): void
    {
        [$requester, $reviewer, $item] = $this->fixtures();

        $activity = app(LoanRequestService::class)->submit($requester, $this->requestData($item));
        $this->actingAs($requester);
        app(LoanRequestService::class)->cancel($activity);

        $this->assertContains('Pengajuan dibatalkan', $requester->notifications()->get()->pluck('data.title'));
        $this->assertContains('Pengajuan dibatalkan pemohon', $reviewer->notifications()->get()->pluck('data.title'));
    }

    private function fixtures(): array
    {
        $this->seed(RoleSeeder::class);

        $unit = G001M001Unit::query()->create(['name' => 'Unit Pengujian']);
        $requester = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        $requester->assignRole(config('role.sarpras'));

        $reviewer = User::factory()->create();
        $management = G002M003ItemManagement::query()->create(['name' => 'Pengelola Elektronik']);
        $management->users()->attach($reviewer);

        $item = G002M007Item::query()->create([
            'g001_m001_unit_id' => $unit->id,
            'g002_m003_item_management_id' => $management->id,
            'name' => 'Proyektor',
            'is_borrowable' => true,
            'quantity' => 2,
            'available_quantity' => 2,
            'status' => 'tersedia',
        ]);

        return [$requester, $reviewer, $item];
    }

    private function requestData(G002M007Item $item): array
    {
        return [
            'name' => 'Rapat koordinasi',
            'description' => 'Rapat bulanan unit.',
            'start_time' => '2026-09-01 09:00:00',
            'end_time' => '2026-09-01 11:00:00',
            'needs' => [
                ['type' => 'item', 'item_id' => $item->id, 'quantity' => 1],
            ],
        ];
    }
}
