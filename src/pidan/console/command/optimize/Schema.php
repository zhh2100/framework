<?php
namespace pidan\console\command\optimize;

use Exception;
use pidan\console\Command;
use pidan\console\Input;
use pidan\console\input\Argument;
use pidan\console\input\Option;
use pidan\console\Output;
use pidan\db\PDOConnection;

class Schema extends Command
{
    protected function configure()
    {
        $this->setName('optimize:schema')
            ->addArgument('dir', Argument::OPTIONAL, 'dir name .')
            ->addOption('connection', null, Option::VALUE_REQUIRED, 'connection name .')
            ->addOption('table', null, Option::VALUE_REQUIRED, 'table name .')
            ->setDescription('Build database schema cache.');
    }

    protected function execute(Input $input, Output $output)
    {
        $dir = $input->getArgument('dir') ?: '';

        if ($input->hasOption('table')) {
            $connection = $this->app->db->connect($input->getOption('connection'));
            if (!$connection instanceof PDOConnection) {
                $output->error("only PDO connection support schema cache!");
                return;
            }
            $table = $input->getOption('table');
            if (false === strpos($table, '.')) {
                $dbName = $connection->getConfig('database');
            } else {
                [$dbName, $table] = explode('.', $table);
            }

            if ($table == '*') {
                $table = $connection->getTables($dbName);
            }

            $this->buildDataBaseSchema($connection, (array) $table, $dbName);
        } else {
            if ($dir) {
                $appPath   = $this->app->getBasePath() . $dir . DIRECTORY_SEPARATOR;
                $namespace = 'app\\' . $dir;
            } else {
                $appPath   = $this->app->getBasePath();
                $namespace = 'app';
            }

            $path = $appPath . 'model';
            $list = is_dir($path) ? scandir($path) : [];

            foreach ($list as $file) {
                if (0 === strpos($file, '.')) {
                    continue;
                }
                $class = '\\' . $namespace . '\\model\\' . pathinfo($file, PATHINFO_FILENAME);
                $this->buildModelSchema($class);
            }
        }

        $output->writeln('<info>Succeed!</info>');
    }

    protected function buildModelSchema(string $class): void
    {
        $reflect = new \ReflectionClass($class);
        if (!$reflect->isAbstract() && $reflect->isSubclassOf('\pidan\Model')) {
            try {
                /** @var \pidan\Model $model */
                $model      = new $class;
                $connection = $model->db()->getConnection();
                if ($connection instanceof PDOConnection) {
                    $table = $model->getTable();
                    //预读字段信息
                    $connection->getSchemaInfo($table, true);
                }
            } catch (Exception $e) {

            }
        }
    }

    protected function buildDataBaseSchema(PDOConnection $connection, array $tables, string $dbName): void
    {
        foreach ($tables as $table) {
            //预读字段信息
            $connection->getSchemaInfo("{$dbName}.{$table}", true);
        }
    }
}
