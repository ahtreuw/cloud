<?php declare(strict_types=1);

namespace Vulpes\Cloud\PubSub;

use Exception;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Throwable;
use Vulpes\Cloud\Access\GoogleAccessToken;
use Vulpes\Cloud\CloudClientTrait;

class CloudSubscriberClient
{
    use CloudClientTrait;

    public const string ACCESS_SCOPE = 'https://www.googleapis.com/auth/pubsub';
    public const string SUBSCRIBE_URL = 'https://pubsub.googleapis.com/v1/projects/%s/subscriptions/%s:pull';
    public const string ACKNOWLEDGE_URL = 'https://pubsub.googleapis.com/v1/projects/%s/subscriptions/%s:acknowledge';

    public function __construct(
        protected GoogleAccessToken       $token,
        protected ClientInterface         $client,
        protected RequestFactoryInterface $factory,
        protected string                  $subscription,
        protected bool                    $decode = true
    )
    {
        $this->token = $this->token->withScopes(self::ACCESS_SCOPE);
    }

    /**
     * @param int $maxMessages
     * @return PubSubMessage[]
     * @throws Throwable
     */
    public function pull(int $maxMessages): array
    {
        return $this->processReceivedMessages($this->processResponse(
            $this->client->sendRequest($this->createPullRequest($maxMessages))
        )['receivedMessages'] ?? []);
    }

    /**
     * @throws Throwable
     */
    public function ack(object|array|string ...$messages): void
    {
        if (count($messages) === 0) {
            return;
        }
        $this->processResponse($this->client->sendRequest(
            $this->createAckRequest(...$this->fetchAckIds(...$messages))
        ));
    }

    /**
     * @throws Throwable
     */
    private function createPullRequest(int $maxMessages): RequestInterface
    {
        $request = $this->factory
            ->createRequest('POST', sprintf(self::SUBSCRIBE_URL, $this->token->project_id, $this->subscription))
            ->withHeader('Authorization', "Bearer " . $this->token->getToken())
            ->withHeader('Content-Type', 'application/json');

        $request->getBody()->write(json_encode(['maxMessages' => $maxMessages], JSON_THROW_ON_ERROR));

        return $request;
    }

    /**
     * @throws Throwable
     */
    private function createAckRequest(string ...$ackIds): RequestInterface
    {
        $request = $this->factory
            ->createRequest('POST', sprintf(self::ACKNOWLEDGE_URL, $this->token->project_id, $this->subscription))
            ->withHeader('Authorization', "Bearer " . $this->token->getToken())
            ->withHeader('Content-Type', 'application/json');

        $request->getBody()->write(json_encode(['ackIds' => $ackIds], JSON_THROW_ON_ERROR));

        return $request;
    }

    /**
     * @throws Exception
     */
    private function processReceivedMessages(array $receivedMessages): array
    {
        $pubSubMessages = [];
        foreach ($receivedMessages as $receivedMessage) {
            $receivedMessage['message']['data'] = base64_decode($receivedMessage['message']['data']);

            $this->decode && $receivedMessage['message']['data']
                = json_decode($receivedMessage['message']['data'], true);

            $pubSubMessages[] = new PubSubMessage(
                data: $receivedMessage['message']['data'] ?? null,
                attributes: $receivedMessage['message']['attributes'] ?? [],
                ackId: $receivedMessage['ackId'] ?? null,
                messageId: $receivedMessage['message']['messageId'] ?? null,
                publishTime: $receivedMessage['message']['publishTime'] ?? null,
                scheduleTime: $receivedMessage['message']['scheduleTime'] ?? null
            );
        }
        return $pubSubMessages;
    }

    /**
     * @throws Exception
     */
    private function fetchAckIds(object|array|string ...$messages): array
    {
        $ackIds = [];
        foreach ($messages as $message) {
            if (is_string($message)) {
                $ackIds[] = $message;
                continue;
            }
            if (is_array($message) && isset($message['ackId'])) {
                $ackIds[] = $message['ackId'];
                continue;
            }
            if (is_object($message) && property_exists($message, 'ackId')) {
                $ackIds[] = $message->ackId;
                continue;
            }
            throw new Exception('Acknowledge ID not found in message');
        }
        return $ackIds;
    }
}
