<?php

namespace Database\Seeders;

use App\Models\HiringStage;
use App\Models\Status;
use Illuminate\Database\Seeder;

class HiringStageSeeder extends Seeder
{
    /**
     * Pipeline order: [code, name, is_terminal].
     *
     * @var list<array{0: string, 1: string, 2?: bool}>
     */
    private const STAGES = [
        ['sourced', 'Sourced'],
        ['screening', 'Screening'],
        ['client_submission', 'Client Submission'],
        ['l1', 'L1 Interview'],
        ['l2', 'L2 Interview'],
        ['hr', 'HR Round'],
        ['offer', 'Offer'],
        ['joined', 'Joined', true],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $activeStatusId = Status::idFor(Status::MODULE_GENERAL, 'active');

        foreach (self::STAGES as $sortOrder => $stage) {
            HiringStage::updateOrCreate(
                ['code' => $stage[0]],
                ['name' => $stage[1], 'is_terminal' => $stage[2] ?? false, 'sort_order' => $sortOrder + 1, 'status_id' => $activeStatusId],
            );
        }
    }
}
