<?php declare(strict_types=1);

namespace Vulpes\Cloud\PubSub;

use DateTimeInterface;

final readonly class PubSubMessage
{
    public function __construct(
        public mixed                             $data = null,
        public array                             $attributes = [],
        public null|string                       $ackId = null,
        public null|string                       $messageId = null,
        public null|string                       $publishTime = null,
        public null|string|DateTimeInterface|int $scheduleTime = null,
    )
    {
    }
}
