<?php declare(strict_types=1);

namespace Vulpes\Cloud;

use Psr\Clock\ClockInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Log\LoggerInterface;
use Vulpes\Cloud\Access\GoogleAccessToken;
use Vulpes\Cloud\Clock\Clock;
use Vulpes\Cloud\Logging\CloudLoggerClient;
use Vulpes\Cloud\PubSub\CloudPublisherClient;
use Vulpes\Cloud\PubSub\CloudSubscriberClient;
use Vulpes\Cloud\Task\CloudTaskClient;

class CloudFactory
{
    public function __construct(
        protected GoogleAccessToken       $token,
        protected ClientInterface         $client,
        protected RequestFactoryInterface $factory,
        protected ClockInterface          $clock = new Clock,
    )
    {
    }

    public function createPublisherClient(
        string $topic, null|string $locationId = null, null|string $queueName = null, bool $encode = true
    ): CloudPublisherClient
    {
        $cloudTaskClient = $locationId && $queueName ? $this->createTaskClient($locationId, $queueName) : null;
        return new CloudPublisherClient($this->token, $this->client, $this->factory, $topic, $cloudTaskClient, $encode);
    }

    public function createSubscriberClient(string $subscription, bool $decode = true): CloudSubscriberClient
    {
        return new CloudSubscriberClient($this->token, $this->client, $this->factory, $subscription, $decode);
    }

    public function createLoggerClient(string $logName = 'default', array $labels = []): LoggerInterface
    {
        return new CloudLoggerClient($this->client, $this->factory, $logName, $labels, $this->token, $this->clock);
    }

    public function createTaskClient(string $locationId, string $queueName): CloudTaskClient
    {
        return new CloudTaskClient($this->client, $this->factory, $this->token, $locationId, $queueName);
    }

}
