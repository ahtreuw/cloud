<?php declare(strict_types=1);

namespace Vulpes\Cloud\Task;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use JsonException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Throwable;
use Vulpes\Cloud\Access\GoogleAccessToken;
use Vulpes\Cloud\CloudClientTrait;

/**
 * @link https://cloud.google.com/tasks/docs/reference/rest/v2/projects.locations.queues.tasks/create
 * @link https://developers.google.com/identity/protocols/oauth2/scopes#cloudtasks
 */
class CloudTaskClient
{
    use CloudClientTrait;

    public const string ACCESS_SCOPE = 'https://www.googleapis.com/auth/cloud-tasks';
    public const string TASKS_URL = "https://cloudtasks.googleapis.com/v2/projects/%s/locations/%s/queues/%s/tasks";

    public function __construct(
        protected ClientInterface         $client,
        protected RequestFactoryInterface $factory,
        protected GoogleAccessToken       $token,
        protected string                  $locationId,
        protected string                  $queueName,
    )
    {
        $this->token = $this->token->withScopes(self::ACCESS_SCOPE);
    }

    /**
     * @throws Throwable
     */
    public function sendTask(array|object $task): array
    {
        return $this->processResponse($this->client->sendRequest(
            $this->createRequest(is_object($task) ? get_object_vars($task) : $task)
        ));
    }

    /**
     * @throws Throwable
     */
    private function createRequest(array|object $task): RequestInterface
    {
        $request = $this->factory
            ->createRequest('POST', sprintf(self::TASKS_URL, $this->token->project_id, $this->locationId, $this->queueName))
            ->withHeader('Authorization', "Bearer " . $this->token->getToken())
            ->withHeader('Content-Type', 'application/json');

        $request->getBody()->write($this->createRequestBody($task));

        return $request;
    }

    /**
     * @throws Throwable
     */
    private function createRequestBody(object|array $task): string
    {
        $requestBody = [
            'task' => [
                'httpRequest' => array_filter([
                    'httpMethod' => $task['httpMethod'] ?? $task['method'] ?? 'POST',
                    'url' => $task['url'],
                    'headers' => $task['headers'] ?? null,
                    'body' => $task['body'] ?? null,
                ])
            ],
            'responseView' => $task['taskResponseView'] ?? 'BASIC', // (BASIC|FULL)
        ];

        ($task['scheduleTime'] ?? null) &&
        $requestBody['task']['scheduleTime'] = $this->prepareTime($task['scheduleTime']);

        return json_encode($requestBody, JSON_THROW_ON_ERROR);
    }

    /**
     * @throws Throwable
     */
    private function prepareTime(mixed $scheduleTime): string
    {
        if (is_numeric($scheduleTime)) {
            $dateTime = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            if (599616000 < $scheduleTime) { // gt?1989-01-01
                $scheduleTime = $dateTime->setTimestamp($scheduleTime);
            } else {
                $scheduleTime = $dateTime->setTimestamp($dateTime->getTimestamp() + $scheduleTime);
            }
        }
        if (is_string($scheduleTime)) {
            $scheduleTime = new DateTimeImmutable($scheduleTime, new DateTimeZone('UTC'));
        }
        if ($scheduleTime instanceof DateTimeInterface) {
            return $scheduleTime->format("Y-m-d\TH:i:s\Z");
        }
        throw new Exception('Invalid schedule time');
    }

}
