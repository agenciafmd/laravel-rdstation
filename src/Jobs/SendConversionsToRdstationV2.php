<?php

namespace Agenciafmd\Rdstation\Jobs;

use Agenciafmd\Frontend\Exceptions\RniServiceException;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class SendConversionsToRdstationV2 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $api;

    protected $data;

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    public function handle(): void
    {
        if (!config('laravel-rdstation.client_id') || !config('laravel-rdstation.client_secret') || !config('laravel-rdstation.refresh_token')) {
            return;
        }

        $this->loadHttpClient();
        $accessToken = $this->accessToken();

        if (!$accessToken) {
            return;
        }

        $response = $this->sendConversion($this->data);

        if ($response->getStatusCode() !== 200) {
            // enviar email pra alguem dizendo que não deu certo e com o payload que foi enviado e o response
        }
    }

    private function loadHttpClient(): void
    {
        $logger = new Logger('Rdstation');
        $logger->pushHandler(new StreamHandler(storage_path('logs/rdstation-' . date('Y-m-d') . '.log')));

        $stack = HandlerStack::create();
        $stack->push(
            Middleware::log(
                $logger,
                new MessageFormatter("{method} {uri} HTTP/{version} {req_body} | RESPONSE: {code} - {res_body}")
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
        return Cache::remember('api-token', now()->addMinutes(60), function () {
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

            return json_decode((string) $response->getBody())->access_token;
        });
    }

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
    }
}
