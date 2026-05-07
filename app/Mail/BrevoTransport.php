<?php

namespace App\Mail;

use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\MessageConverter;
use GuzzleHttp\Client;

class BrevoTransport extends AbstractTransport
{
    protected $apiKey;

    public function __construct(string $apiKey)
    {
        parent::__construct();
        $this->apiKey = $apiKey;
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());
        
        $client = new Client([
            'base_uri' => 'https://api.brevo.com/v3/',
            'headers' => [
                'api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
        
        $from = $email->getFrom()[0];
        $to = array_map(function($addr) {
            return [
                'email' => $addr->getAddress(),
                'name' => $addr->getName() ?: 'Recipient',
            ];
        }, $email->getTo());
        
        $payload = [
            'sender' => [
                'email' => $from->getAddress(),
                'name' => $from->getName() ?: 'SHC TVET',
            ],
            'to' => $to,
            'subject' => $email->getSubject(),
            'htmlContent' => $email->getHtmlBody(),
        ];
        
        $client->post('smtp/email', ['json' => $payload]);
    }

    public function __toString(): string
    {
        return 'brevo';
    }
}
