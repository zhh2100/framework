<?php
declare (strict_types = 1);

namespace pidan\console\command;

use pidan\console\Command;
use pidan\console\Input;
use pidan\console\Output;

class Version extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('version')
            ->setDescription('show pidanphp framework version');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('v' . $this->app->version());
    }

}
