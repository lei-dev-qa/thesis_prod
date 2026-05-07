<?php

namespace App\Notifications\Application;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Application\Application;

class ApplicationResubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    
    protected $application;

    public function __construct(Application $application)
    {
        $this->application = $application;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->subject('Application Resubmitted - ' . $this->application->title_of_assessment_applied_for)
                    ->greeting('Hello Admin,')
                    ->line('An applicant has resubmitted their application after making corrections.')
                    ->line('**Applicant:** ' . $this->application->firstname . ' ' . $this->application->surname)
                    ->line('**Assessment:** ' . $this->application->title_of_assessment_applied_for)
                    ->line('**Resubmitted:** ' . now()->format('M d, Y h:i A'))
                    ->action('Review Application', route('admin.applications.show', $this->application))
                    ->line('Please review the updated application.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'application_id' => $this->application->id,
            'resubmitted_at' => now(),
        ];
    }
}
