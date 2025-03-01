<?php declare(strict_types=1);

namespace Vulpes\Cloud\PubSub;

use DateTimeInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;
use Throwable;
use Vulpes\Cloud\Access\GoogleAccessToken;
use Vulpes\Cloud\CloudClientTrait;
use Vulpes\Cloud\Task\CloudTaskClient;

class CloudPublisherClient
{
    use CloudClientTrait;

    public const string ACCESS_SCOPE = 'https://www.googleapis.com/auth/pubsub';
    public const string PUBLISH_URL = 'https://pubsub.googleapis.com/v1/projects/%s/topics/%s:publish';

    public function __construct(
        protected GoogleAccessToken       $token,
        protected ClientInterface         $client,
        protected RequestFactoryInterface $factory,
        protected string                  $topic,
        protected null|CloudTaskClient    $cloudTaskClient = null,
        protected bool                    $encode = true,
    )
    {
        $this->token = $this->token->withScopes(self::ACCESS_SCOPE);
    }

    /**
     * @throws Throwable
     */
    public function publish(array|object ...$messages): array
    {
        $scheduled = [];
        $immediate = [];

        foreach ($messages as $message) {
            if (is_object($message) && property_exists($message, 'scheduleTime')) {
                $scheduled[] = ['message' => get_object_vars($message), 'scheduleTime' => $message->scheduleTime];
                continue;
            }

            if (is_array($message) && isset($message['scheduleTime'])) {
                $scheduled[] = ['message' => $message, 'scheduleTime' => $message['scheduleTime']];
                continue;
            }

            $immediate[] = $message;
        }

        foreach ($scheduled as $message) {
            $this->sendAsTask($message['message'], $message['scheduleTime']);
        }

        if ($immediate) {
            return $this->processResponse(
                $this->client->sendRequest($this->createRequest(...$immediate))
            )['messageIds'];
        }

        return [];
    }

    public function createMessage(
        mixed                             $data = null,
        array                             $attributes = [],
        null|string|DateTimeInterface|int $scheduleTime = null,
    ): PubSubMessage
    {
        return new PubSubMessage(
            data: $data,
            attributes: $attributes,
            scheduleTime: $scheduleTime
        );
    }

    /**
     * @throws Throwable
     */
    private function createRequest(array ...$messages): RequestInterface
    {
        $request = $this->factory
            ->createRequest('POST', sprintf(self::PUBLISH_URL, $this->token->project_id, $this->topic))
            ->withHeader('Authorization', "Bearer " . $this->token->getToken())
            ->withHeader('Content-Type', 'application/json');

        foreach ($messages as $i => $item) {
            $this->encode && $item['data'] = json_encode($item['data']);
            $messages[$i]['data'] = base64_encode($item['data']);
        }

        $request->getBody()->write(json_encode(['messages' => $messages], JSON_THROW_ON_ERROR));

        return $request;
    }

    /**
     * @throws Throwable
     */
    private function sendAsTask(array $message, mixed $scheduleTime): void
    {
        if ($this->cloudTaskClient === null) {
            throw new RuntimeException('CloudTaskClient not set..');
        }
        $this->cloudTaskClient->sendTask([
            'httpMethod' => 'POST',
            'url' => sprintf(self::PUBLISH_URL, $this->token->project_id, $this->topic),
            'headers' => [
                'Content-Type' => "application/json",
                'Authorization' => "Bearer " . $this->token->getToken(),
            ],
            'body' => base64_encode(json_encode([
                'attributes' => $message['attributes'],
                'data' => base64_encode($this->encode ? json_encode($message['data'], JSON_THROW_ON_ERROR) : $message['data']),
            ], JSON_THROW_ON_ERROR)),
            'scheduleTime' => $scheduleTime,
        ]);
    }
}
