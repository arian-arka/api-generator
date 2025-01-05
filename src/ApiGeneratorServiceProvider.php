<?php

namespace EcliPhp\ApiGenerator;

use EcliPhp\ApiGenerator\Command\ApiGenerateCommand;
use Illuminate\Support\ServiceProvider;

class ApiGeneratorServiceProvider extends ServiceProvider
{
    public function register()
    {

    }
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/api-generator.php' => config_path('api-generator.php'),
        ]);
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        if ($this->app->runningInConsole()) {
            $this->commands([
                ApiGenerateCommand::class,
            ]);
        }
    }


//    public function configurePackage(Package $package): void
//    {
//        /*
//         * This class is a Package Service Provider
//         *
//         * More info: https://github.com/spatie/laravel-package-tools
//         */
//        $package
//            ->name('api-generator')
//            ->hasConfigFile()
//            ->hasCommand(ApiGenerateCommand::class)
//            ->hasRoute('web');
//    }

}
