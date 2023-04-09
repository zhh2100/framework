<?php
declare (strict_types = 1);

namespace pidan\service;

use pidan\Service;
use pidan\Validate;

/**
 * 验证服务类
 */
class ValidateService extends Service
{
    public function boot()
    {
        Validate::maker(function (Validate $validate) {
            $validate->setLang(app('lang'));
            $validate->setDb(app('db'));
            $validate->setRequest(app('request'));
        });
    }
}
