<?php
namespace Rnr\YandexKassa\Providers;


use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use Rnr\YandexKassa\Interfaces\YandexKassaInterface;
use Rnr\YandexKassa\YandexKassa;
use Illuminate\Contracts\Config\Repository as Config;

class YandexKassaProvider extends ServiceProvider
{
    public function boot()
    {
        if (!$this->app->routesAreCached()) {
            require __DIR__ . '/routes.php';
        }
    }
    
    public function register()
    {
        $this->app->bind(YandexKassaInterface::class, function (Container $app) {
            /** @var Config $config */
            $config = $app->make(Config::class);

            return $app->make(YandexKassa::class, [
                'options' => $config->get('yandex-kassa')
            ]);
        });
    }
}