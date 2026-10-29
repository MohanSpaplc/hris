<?php

namespace App\Notifications;

use App\Models\Job;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Emailed to a recruiter when they are assigned to a job. Queued, and sent only after the job is saved.
 */
class JobAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Job $job, public User $assignedBy, public bool $isPrimary)
    {
        $this->afterCommit();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $job = $this->job->loadMissing(['client', 'country', 'practiceArea']);
        $assigner = trim("{$this->assignedBy->first_name} {$this->assignedBy->last_name}");

        return (new MailMessage)
            ->subject("Job assigned: {$job->job_code} · {$job->job_title}")
            ->greeting("Hello {$notifiable->first_name},")
            ->line("{$assigner} assigned you".($this->isPrimary ? ' as the **primary recruiter**' : '').' to this job:')
            ->line("**{$job->job_code} · {$job->job_title}**")
            ->line('Client: '.($job->client?->name ?? 'Internal'))
            ->line('Location: '.collect([$job->work_location, $job->country?->name])->filter()->implode(', '))
            ->line("Positions: {$job->no_of_positions} · Priority: ".ucfirst($job->priority))
            ->action('Open the job', rtrim(config('hris.frontend_url'), '/')."/jobs/{$job->id}");
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'job_id' => $this->job->id,
            'assigned_by' => $this->assignedBy->id,
            'is_primary' => $this->isPrimary,
        ];
    }
}
