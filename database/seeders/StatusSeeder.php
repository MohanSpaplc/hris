<?php

namespace Database\Seeders;

use App\Models\Status;
use Illuminate\Database\Seeder;

class StatusSeeder extends Seeder
{
    /**
     * Statuses per module: [code, name, color, is_default, is_final].
     *
     * @var array<string, list<array{0: string, 1: string, 2: string, 3?: bool, 4?: bool}>>
     */
    private const STATUSES = [
        'general' => [
            ['active', 'Active', 'success', true],
            ['inactive', 'Inactive', 'secondary'],
        ],
        'job' => [
            ['draft', 'Draft', 'secondary', true],
            ['open', 'Open', 'success'],
            ['on_hold', 'On Hold', 'warning'],
            ['closed', 'Closed', 'dark', false, true],
            ['filled', 'Filled', 'primary', false, true],
            ['cancelled', 'Cancelled', 'danger', false, true],
        ],
        'candidate' => [
            ['active', 'Active', 'success', true],
            ['blacklisted', 'Blacklisted', 'danger'],
            ['placed', 'Placed', 'primary'],
            ['inactive', 'Inactive', 'secondary'],
        ],
        'document' => [
            ['pending_verification', 'Pending Verification', 'warning', true],
            ['verified', 'Verified', 'success', false, true],
            ['rejected', 'Rejected', 'danger', false, true],
        ],
        'application' => [
            ['active', 'Active', 'info', true],
            ['on_hold', 'On Hold', 'warning'],
            ['rejected', 'Rejected', 'danger', false, true],
            ['withdrawn', 'Withdrawn', 'secondary', false, true],
            ['hired', 'Hired', 'success', false, true],
        ],
        'interview' => [
            ['scheduled', 'Scheduled', 'info', true],
            ['rescheduled', 'Rescheduled', 'warning'],
            ['completed', 'Completed', 'success', false, true],
            ['cancelled', 'Cancelled', 'danger', false, true],
            ['no_show', 'No Show', 'dark', false, true],
        ],
        'interview_result' => [
            ['selected', 'Selected', 'success'],
            ['rejected', 'Rejected', 'danger'],
            ['on_hold', 'On Hold', 'warning'],
            ['next_round', 'Next Round', 'info'],
        ],
        'offer' => [
            ['draft', 'Draft', 'secondary', true],
            ['pending_approval', 'Pending Approval', 'warning'],
            ['approved', 'Approved', 'info'],
            ['rejected', 'Rejected', 'danger', false, true],
            ['sent', 'Sent', 'primary'],
            ['accepted', 'Accepted', 'success'],
            ['declined', 'Declined', 'danger', false, true],
            ['revised', 'Revised', 'secondary', false, true],
            ['expired', 'Expired', 'dark', false, true],
            ['withdrawn', 'Withdrawn', 'dark', false, true],
            ['joined', 'Joined', 'success', false, true],
            ['no_show', 'No Show', 'danger', false, true],
        ],
        'approval_action' => [
            ['submitted', 'Submitted', 'info', true],
            ['approved', 'Approved', 'success', false, true],
            ['rejected', 'Rejected', 'danger', false, true],
            ['cancelled', 'Cancelled', 'secondary', false, true],
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::STATUSES as $module => $statuses) {
            foreach ($statuses as $sortOrder => $status) {
                Status::updateOrCreate(
                    ['module' => $module, 'code' => $status[0]],
                    [
                        'name' => $status[1],
                        'color' => $status[2],
                        'is_default' => $status[3] ?? false,
                        'is_final' => $status[4] ?? false,
                        'sort_order' => $sortOrder + 1,
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
