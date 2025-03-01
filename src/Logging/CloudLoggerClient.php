<?php declare(strict_types=1);

namespace Vulpes\Cloud\Logging;

use Psr\Clock\ClockInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Stringable;
use Throwable;
use Vulpes\Cloud\Access\GoogleAccessToken;
use Vulpes\Cloud\Clock\Clock;
use Vulpes\Cloud\CloudClientTrait;

class CloudLoggerClient implements LoggerInterface
{
    use LoggerTrait, CloudClientTrait;

    public const string ACCESS_SCOPE = 'https://www.googleapis.com/auth/logging.write';
    public const string LOGGING_URL = 'https://logging.googleapis.com/v2/entries:write';
    const array SEVERITY_MAP = [
        "debug" => "DEBUG", "info" => "INFO", "notice" => "NOTICE", "warn" => "WARNING",
        "warning" => "WARNING", "err" => "ERROR", "error" => "ERROR", "crit" => "CRITICAL",
        "critical" => "CRITICAL", "alert" => "ALERT", "emerg" => "EMERGENCY", "emergency" => "EMERGENCY",
        "7" => "DEBUG", "6" => "INFO", "5" => "NOTICE", "4" => "WARNING",
        "3" => "ERROR", "2" => "CRITICAL", "1" => "ALERT", "0" => "EMERGENCY",
        "100" => "DEBUG", "200" => "INFO", "300" => "NOTICE", "400" => "WARNING", "500" => "ERROR",
        "600" => "CRITICAL", "700" => "ALERT", "800" => "EMERGENCY",
    ];

    public function __construct(
        protected ClientInterface         $client,
        protected RequestFactoryInterface $factory,
        protected string                  $logName = 'default',
        protected array                   $labels = [],
        protected GoogleAccessToken       $token = new GoogleAccessToken,
        protected ClockInterface          $clock = new Clock,
        protected                         $resourceType = 'global'
    )
    {
        $this->token = $this->token->withScopes(self::ACCESS_SCOPE);
    }

    public function with(string $logName = 'default', array $labels = []): CloudLoggerClient|static
    {
        $clone = clone $this;
        $clone->logName = $logName;
        $clone->labels = $labels;
        return $clone;
    }

    /**
     * @throws Throwable
     */
    public function log($level, Stringable|string $message, array $context = [], array $labels = []): void
    {
        $this->processResponse($this->client->sendRequest(
            $this->createRequest($this->createEntry($level, $message, $context, $labels))
        ));
    }

    /**
     * @throws Throwable
     */
    private function createRequest(array ...$entries): RequestInterface
    {
        $request = $this->factory
            ->createRequest('POST', self::LOGGING_URL)
            ->withHeader('Authorization', "Bearer " . $this->token->getToken())
            ->withHeader('Content-Type', 'application/json');

        $request->getBody()->write(json_encode(['entries' => $entries], JSON_THROW_ON_ERROR));

        return $request;
    }

    public function createEntry($level, Stringable|string $message, array $context = [], array $labels = []): array
    {
        return [
            "logName" => "projects/{$this->token->project_id}/logs/$this->logName",
            "resource" => ["type" => $this->resourceType],
            "severity" => self::SEVERITY_MAP[strtolower(trim(strval($level)))] ?? "DEFAULT",
            "timestamp" => $this->clock->now()->format("Y-m-d\TH:i:s\Z"),
            "jsonPayload" => array_merge($context, ['message' => $message]),
            "labels" => array_merge($this->labels, $labels)
        ];
    }
}
