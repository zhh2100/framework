<?php
declare (strict_types = 1);

namespace pidan\initializer;

use pidan\App;
use pidan\service\ModelService;
//use pidan\service\PaginatorService;
use pidan\service\ValidateService;

/**
 * 注册系统服务
 */
class RegisterService
{

    protected $services = [
        #PaginatorService::class,
        ValidateService::class,
        ModelService::class,
    ];

    public function init(App $app)
    {
        $file = $app->getRootPath() . 'vendor/services.php';

        $services = $this->services;

        if (is_file($file)) {
            $services = array_merge($services, include $file);
        }

        foreach ($services as $service) {
            if (class_exists($service)) {
                $app->register($service);
            }
        }
    }
}
