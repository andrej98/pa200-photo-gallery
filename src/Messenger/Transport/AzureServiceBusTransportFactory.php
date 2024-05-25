<?php

namespace App\Messenger\Transport;

use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Exception\TransportException;

class AzureServiceBusTransportFactory implements TransportFactoryInterface
{
    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        if (0 !== strpos($dsn, 'custom://')) {
            throw new TransportException('Invalid DSN');
        }

        $endpoint = $options['endpoint'] ?? null;
        $sasKeyName = $options['sasKeyName'] ?? null;
        $sasKeyValue = $options['sasKeyValue'] ?? null;
        $queue = $options['queue'] ?? null;

        return new AzureServiceBusTransport($endpoint, $sasKeyName, $sasKeyValue, $queue, $serializer);
    }

    public function supports(string $dsn, array $options): bool
    {
        return 0 === strpos($dsn, 'custom://');
    }
}
