## Laravel - RD Station

[![Downloads](https://img.shields.io/packagist/dt/agenciafmd/laravel-rdstation.svg?style=flat-square)](https://packagist.org/packages/agenciafmd/laravel-rdstation)
[![Licença](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)

- Envia as conversões para o RD Station

## Instalação

```bash
composer require agenciafmd/laravel-rdstation:dev-master
```

## Configuração

Para que a integração seja realizada, precisamos do **token público**

Para isso, vamos em **Perfil > Integrações**

![Perfil > Integrações](https://github.com/agenciafmd/laravel-rdstation/raw/master/docs/screenshot01.jpg "Perfil > Integrações")

Agora, vamos em **Dados da Integração > Token Público**

![Dados da Integração > Token Público](https://github.com/agenciafmd/laravel-rdstation/raw/master/docs/screenshot02.jpg "Dados da Integração > Token Público")

Colocamos esta chave no nosso .env

```dotenv
RDSTATION_PUBLIC_KEY=VYfa6Oo1oaCIeQ68Ase9dSOBPdgRvWtJ
```

## Uso

Envie os campos no formato de array para o SendConversionsToRdstation.

O campo **email** é obrigatório =)

Para que o processo funcione pelos **jobs**, é preciso passar os valores dos cookies conforme mostrado abaixo.

```php
use Agenciafmd\Rdstation\Jobs\SendConversionsToRdstation;

$data['email'] = 'irineu@fmd.ag';
$data['nome'] = 'Irineu Junior';

SendConversionsToRdstation::dispatch($data + [
        'identificador' => 'seja-um-parceiro',
        'utm_campaign' => Cookie::get('utm_campaign', ''),
        'utm_content' => Cookie::get('utm_content', ''),
        'utm_medium' => Cookie::get('utm_medium', ''),
        'utm_source' => Cookie::get('utm_source', ''),
        'utm_term' => Cookie::get('utm_term', ''),
        'gclid_' => Cookie::get('gclid', ''),
        'cid' => Cookie::get('cid', ''),
    ])
    ->delay(5)
    ->onQueue('low');
```

Note que no nosso exemplo, enviamos o job para a fila **low**.

Certifique-se de estar rodando no seu queue:work esteja semelhante ao abaixo.

```shell
php artisan queue:work --tries=3 --delay=5 --timeout=60 --queue=high,default,low
```