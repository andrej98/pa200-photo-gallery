<?php

namespace App\Messenger\Transport;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class AzureServiceBusTransport implements TransportInterface, SenderInterface
{
    private $endpoint;
    private $sasKeyName;
    private $sasKeyValue;
    private $queue;
    private $serializer;

    public function __construct(string $endpoint, string $sasKeyName, string $sasKeyValue, string $queue, SerializerInterface $serializer)
    {
        $this->endpoint = $endpoint;
        $this->sasKeyName = $sasKeyName;
        $this->sasKeyValue = $sasKeyValue;
        $this->queue = $queue;
        $this->serializer = $serializer;
    }

    public function send(Envelope $envelope): Envelope
    {
        $message = $this->serializer->encode($envelope)['body'];
        $this->sendMessage($message);

        return $envelope;
    }

    private function sendMessage($message)
    {
        $resourceUri = "https://{$this->endpoint}.servicebus.windows.net/{$this->queue}/messages";
        $sasToken = $this->createSasToken($resourceUri);

        $headers = [
            'Authorization: ' . $sasToken,
            'Content-Type: application/atom+xml;type=entry;charset=utf-8',
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $resourceUri);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $message);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new \Exception('Error: ' . curl_error($ch));
        }

        curl_close($ch);
    }

    private function createSasToken($resourceUri)
    {
        $expiry = time() + 3600;
        $stringToSign = urlencode($resourceUri) . "\n" . $expiry;
        $signature = base64_encode(hash_hmac('sha256', $stringToSign, $this->sasKeyValue, true));
        return "SharedAccessSignature sr=" . urlencode($resourceUri) . "&sig=" . urlencode($signature) . "&se=" . $expiry . "&skn=" . $this->sasKeyName;
    }

    public function get(): iterable
    {
        // Implement receive logic if necessary
    }

    public function ack(Envelope $envelope): void
    {
        // Implement ack logic if necessary
    }

    public function reject(Envelope $envelope): void
    {
        // Implement reject logic if necessary
    }
}