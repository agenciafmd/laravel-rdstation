<?php

namespace Agenciafmd\Rdstation\Jobs;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Message;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Mail;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class SendConversionsToRdstation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $data;

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    public function handle()
    {
        if (!config('laravel-rdstation.public_key')) {
            return false;
        }

        $client = $this->getClientRequest();

        $formParams = [
                'token_rdstation' => config('laravel-rdstation.public_key'),
            ] + $this->data;

        $response = $client->request('POST', 'https://www.rdstation.com.br/api/1.2/conversions', [
            'form_params' => $formParams,
        ]);

        if (($response->getStatusCode() !== 200) && (config('laravel-rdstation.error_email'))) {
            Mail::raw($response->getBody(), function (Message $message) {
                $message->to(config('laravel-rdstation.error_email'))
                    ->subject('[RDStation][' . config('app.url') . '] - Falha na integração - ' . now()->format('d/m/Y H:i:s'));
            });
        }
    }

    private function getClientRequest()
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

        return new Client([
            'timeout' => 60,
            'connect_timeout' => 60,
            'http_errors' => false,
            'verify' => false,
            'handler' => $stack,
        ]);
    }
}
