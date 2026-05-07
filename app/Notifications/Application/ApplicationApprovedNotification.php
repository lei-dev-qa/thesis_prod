<?php

namespace App\Notifications\Application;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Application\Application;

class ApplicationApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    protected $application;

    /**
     * Create a new notification instance.
     */
    public function __construct(Application $application)
    {
        $this->application = $application;
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
        return (new MailMessage)
            ->subject('Application Approved - ' . $this->application->title_of_assessment_applied_for)
            ->greeting('Hello ' . $this->application->firstname . '!')
            ->line('We are pleased to inform you that your application has been **approved**.')
            ->line('**Application Details:**')
            ->line('• Assessment: ' . $this->application->title_of_assessment_applied_for)
            ->line('• Schedule: ' . $this->application->schedule)
            ->line('• Approved Date: ' . $this->application->reviewed_at?->format('M d, Y'))
            ->line('**Next Steps:**')
            ->line('Please wait for further instructions regarding your schedule.')
            ->action('View Application', route('applicant.applications.show', $this->application))
            ->line('Thank you for choosing SHC-TVET Training and Assessment Center!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
