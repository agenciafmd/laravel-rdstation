<?php

declare(strict_types=1);

namespace Agenciafmd\Rdstation\Jobs;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

final class SendConversionsToRdstation implements ShouldQueue
{
    use Queueable;

    protected Client $api;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(protected array $data = []) {}

    public function handle(): void
    {
        if (! config('laravel-rdstation.client_id') || ! config('laravel-rdstation.client_secret') || ! config('laravel-rdstation.refresh_token')) {
            return;
        }

        $this->loadHttpClient();
        $accessToken = $this->accessToken();

        if (! $accessToken) {
            return;
        }

        $this->sendConversion($this->data);
    }

    private function loadHttpClient(): void
    {
        $logger = new Logger('Rdstation');
        $logger->pushHandler(new StreamHandler(storage_path('logs/rdstation-' . date('Y-m-d') . '.log')));

        $stack = HandlerStack::create();
        $stack->push(
            Middleware::log(
                $logger,
                new MessageFormatter('{method} {uri} HTTP/{version} {req_body} | RESPONSE: {code} - {res_body}')
            )
        );

        $this->api = new Client([
            'timeout' => 60,
            'connect_timeout' => 60,
            'http_errors' => false,
            'verify' => false,
            'handler' => $stack,
        ]);
    }

    private function accessToken(): string
    {
        return Cache::remember('api-token', now()->addMinutes(40), function (): string {
            $response = $this->api->post('https://api.rd.services/auth/token', [
                'json' => [
                    'client_id' => config('laravel-rdstation.client_id'),
                    'client_secret' => config('laravel-rdstation.client_secret'),
                    'refresh_token' => config('laravel-rdstation.refresh_token'),
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                return '';
            }

            $body = json_decode((string) $response->getBody(), true);
            $accessToken = is_array($body) ? ($body['access_token'] ?? null) : null;

            return is_string($accessToken) ? $accessToken : '';
        });
    }

    /**
     * @param array<string, mixed> $data
     * @throws GuzzleException
     */
    private function sendConversion(array $data = []): void
    {
        $response = $this->api->post('https://api.rd.services/platform/events?event_type=conversion', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken(),
            ],
            'json' => [
                'event_type' => 'CONVERSION',
                'event_family' => 'CDP',
                'payload' => $data,
            ],
        ]);

        $errorEmail = config('laravel-rdstation.error_email');
        $appUrl = config('app.url');
        $appUrl = is_string($appUrl) ? $appUrl : '';

        if (($response->getStatusCode() !== 200) && is_string($errorEmail) && $errorEmail !== '') {
            Mail::raw((string) $response->getBody(), function (Message $message) use ($errorEmail, $appUrl): void {
                $message->to($errorEmail)
                    ->subject('[RDStation][' . $appUrl . '] - Falha na integração - ' . now()->format('d/m/Y H:i:s'));
            });
        }
    }
}
