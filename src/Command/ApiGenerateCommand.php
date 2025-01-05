<?php
namespace EcliPhp\ApiGenerator\Command;

use EcliPhp\ApiGenerator\Class\ExtractRoutes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ApiGenerateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api-generator:all {path?} {--show}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate API.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $data = json_encode((new ExtractRoutes())->all(), JSON_PRETTY_PRINT);
        if($this->hasArgument('path'))
            File::put($this->argument('path'),$data);
        if($this->option('show'))
            $this->info($data);
    }
}
