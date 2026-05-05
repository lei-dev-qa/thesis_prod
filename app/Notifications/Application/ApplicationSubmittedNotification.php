<?php

namespace App\Notifications\Application;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Application\Application;

class ApplicationSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    
    protected $application;

    public function __construct(Application $application)
    {
        $this->application = $application;
    }

    /**
     * Get the notification's delivery channels.
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
        $applicantName = trim($this->application->firstname . ' ' . $this->application->surname);
        $ncProgram = $this->application->title_of_assessment_applied_for;
        $applicationType = $this->application->application_type;
        
        return (new MailMessage)
            ->subject('Application Successfully Submitted - ' . $ncProgram)
            ->greeting('Hello ' . $applicantName . ',')
            ->line('Your application for **' . $ncProgram . '** has been successfully submitted.')
            ->line('**Application Details:**')
            ->line('• Application Type: ' . $applicationType)
            ->line('• NC Program: ' . $ncProgram)
            ->line('• Submission Date: ' . $this->application->created_at->format('F d, Y'))
            ->line('**Next Step:**')
            ->line('Please proceed with the payment and upload your proof of payment in the applicant dashboard.')
            ->action('Go to Dashboard', route('applicant.dashboard'))
            ->line('Thank you,')
            ->salutation('SHC-TVET Training and Assessment Centre');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'application_id' => $this->application->id,
            'application_type' => $this->application->application_type,
            'nc_program' => $this->application->title_of_assessment_applied_for,
            'message' => 'Your application has been successfully submitted.',
        ];
    }
}
