<?php

declare(strict_types=1);

namespace Agenciafmd\Rdstation\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Mail\Message;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;

#[Backoff([10, 30, 60])]
#[Tries(4)]
final class SendConversionsToRdstation implements ShouldQueue
{
    use Queueable;

    private ?LoggerInterface $logger = null;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(private readonly array $data = []) {}

    public function handle(): void
    {
        if (! config('laravel-rdstation.client_id') || ! config('laravel-rdstation.client_secret') || ! config('laravel-rdstation.refresh_token')) {
            return;
        }

        $accessToken = $this->accessToken();

        if ($accessToken === '') {
            return;
        }

        $this->sendConversion($accessToken, $this->data);
    }

    public function failed(?Throwable $exception): void
    {
        $this->logger()->error('[RDStation] job failed permanently', [
            'exception' => $exception,
        ]);

        $this->notifyFailure($exception?->getMessage() ?? 'Job failed after all retry attempts.');
    }

    private function accessToken(): string
    {
        return Cache::remember('rdstation-api-token', now()->addMinutes(40), function (): string {
            $response = $this->httpClient()->post('https://api.rd.services/auth/token', [
                'client_id' => config('laravel-rdstation.client_id'),
                'client_secret' => config('laravel-rdstation.client_secret'),
                'refresh_token' => config('laravel-rdstation.refresh_token'),
            ]);

            if ($response->failed()) {
                return '';
            }

            $accessToken = $response->json('access_token');

            return is_string($accessToken) ? $accessToken : '';
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function sendConversion(string $accessToken, array $data): void
    {
        $response = $this->httpClient()
            ->withToken($accessToken)
            ->post('https://api.rd.services/platform/events?event_type=conversion', [
                'event_type' => 'CONVERSION',
                'event_family' => 'CDP',
                'payload' => $data,
            ]);

        if ($response->successful()) {
            return;
        }

        $this->notifyFailure($response->body());
    }

    private function notifyFailure(string $reason): void
    {
        $errorEmail = config('laravel-rdstation.error_email');

        if (! is_string($errorEmail) || $errorEmail === '') {
            return;
        }

        $appUrl = config('app.url');
        $appUrl = is_string($appUrl) ? $appUrl : '';

        Mail::raw($reason, function (Message $message) use ($errorEmail, $appUrl): void {
            $message->to($errorEmail)
                ->subject('[RDStation][' . $appUrl . '] - Falha na integração - ' . now()->format('d/m/Y H:i:s'));
        });
    }

    private function httpClient(): PendingRequest
    {
        return Http::connectTimeout(10)
            ->timeout(30)
            ->withRequestMiddleware(function (RequestInterface $request): RequestInterface {
                $this->logger()->info(sprintf('%s %s', $request->getMethod(), $request->getUri()));

                return $request;
            })
            ->withResponseMiddleware(function (ResponseInterface $response): ResponseInterface {
                $this->logger()->info('RESPONSE: ' . $response->getStatusCode());

                return $response;
            });
    }

    private function logger(): LoggerInterface
    {
        return $this->logger ??= Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/rdstation-' . now()->format('Y-m-d') . '.log'),
        ]);
    }
}
