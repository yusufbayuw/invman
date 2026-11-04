<?php
namespace Tests\Feature;

use App\Models\{OperationalOverride, User};
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OperationalAuditTimestampTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_audit_uses_application_clock_and_retains_timezone_instead_of_database_default(): void
    {
        config(['app.timezone' => 'Asia/Jakarta']);
        Carbon::setTestNow(Carbon::parse('2026-11-05 06:00:00', 'Asia/Jakarta'));
        try {
            $user = User::factory()->create();
            $entry = OperationalOverride::create(['actor_id' => $user->id, 'entity_type' => 'loan',
                'entity_id' => 'synthetic-loan', 'action' => 'qa_timestamp', 'reason' => 'Synthetic audit clock validation.',
                'details' => ['recorded_timezone' => 'untrusted-input', 'reservation_id' => 'synthetic-reservation']]);
            $this->assertSame('2026-11-05 06:00:00', $entry->fresh()->created_at->format('Y-m-d H:i:s'));
            $this->assertSame('Asia/Jakarta', $entry->fresh()->details['recorded_timezone']);
            $this->assertSame('synthetic-reservation', $entry->fresh()->details['reservation_id']);
            $this->assertSame('2026-11-05 06:00:00', DB::table('operational_overrides')->where('id', $entry->id)->value('created_at'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_reading_legacy_audit_does_not_invent_timezone_or_rewrite_timestamp(): void
    {
        $user = User::factory()->create();
        $id = DB::table('operational_overrides')->insertGetId(['actor_id' => $user->id, 'entity_type' => 'loan',
            'entity_id' => 'legacy-loan', 'action' => 'legacy_action', 'reason' => 'Historical audit timestamp.',
            'details' => json_encode(['reservation_id' => 'legacy-reservation']), 'created_at' => '2026-11-04 23:00:00']);
        $entry = OperationalOverride::findOrFail($id);
        $this->assertArrayNotHasKey('recorded_timezone', $entry->details);
        $this->assertSame('2026-11-04 23:00:00', $entry->created_at->format('Y-m-d H:i:s'));
    }
}
