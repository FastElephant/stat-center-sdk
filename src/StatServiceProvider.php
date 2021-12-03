<?php


namespace FastElephant\Printer;

use Illuminate\Support\ServiceProvider;

class StatServiceProvider extends ServiceProvider
{
    /**
     * 启动应用服务
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            $this->getConfigFile() => config_path('stat-center.php'),
        ]);
    }

    /**
     * 在容器中注册绑定。
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            $this->getConfigFile(), 'stat-center'
        );
    }

    protected function getConfigFile()
    {
        return __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'stat-center.php';
    }
}
