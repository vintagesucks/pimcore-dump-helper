<?php

namespace App\Commands;

use Druidfi\Mysqldump\Mysqldump;
use LaravelZero\Framework\Commands\Command;
use Phar;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Dotenv\Exception\PathException;
use Symfony\Component\Process\Process;

use function Termwind\{render};

class DatabaseDumpCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'database:dump';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Dump the database';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $binDir = Phar::running(false) === '' ? base_path().'/bin' : dirname(Phar::running(false), 2).'/bin';

        try {
            $dotenv = new Dotenv();
            $dotenv->loadEnv(getcwd().'/.env');
        } catch (PathException $exception) {
            render(<<<HTML
                <span>{$exception->getMessage()}</span>
            HTML);

            return Command::FAILURE;
        }

        try {
            $pdoOptions = [
                'add-locks' => false,
                'add-drop-table' => true,
                'single-transaction' => true,
                'routines' => false,
                'skip-comments' => true,
                'skip-definer' => true,
                'skip-tz-utc' => true,
                'disable-keys' => false,
                'no-autocommit' => false,
            ];

            if (isset($_ENV['DUMP_NO_DATA']) && $noData = array_filter(explode(',', $_ENV['DUMP_NO_DATA']))) {
                // dump tables without data
                $pdoOptions['include-tables'] = $noData;
                $pdoOptions['no-data'] = true;

                $dumper = $this->getDumper($pdoOptions);
                $dumper->start(getcwd().'/dumps/dev/temp-0.sql');

                // dump tables with data
                $pdoOptions['no-data'] = [];
                $pdoOptions['include-tables'] = [];
                $pdoOptions['exclude-tables'] = $noData;

                $dumper = $this->getDumper($pdoOptions);
                $dumper->start(getcwd().'/dumps/dev/temp-1.sql');
            } else {
                // dump all tables
                $dumper = $this->getDumper($pdoOptions);
                $dumper->start(getcwd().'/dumps/dev/temp.sql');
            }

            // remove latest dump
            (new Process(['rm', '-f', './dumps/dev/latest.sql'], getcwd()))->run();

            // concat dumps
            (Process::fromShellCommandline('cat ./dumps/dev/temp*.sql >> ./dumps/dev/concat.sql', getcwd()))->run();

            // format insert statements with process-mysqldump
            (Process::fromShellCommandline($binDir.'/process-mysqldump ./dumps/dev/concat.sql >> ./dumps/dev/latest.sql', getcwd()))->run();

            // remove unwanted lines
            (Process::fromShellCommandline('sed -i "" "/@OLD_UNIQUE_CHECKS/d" ./dumps/dev/latest.sql', getcwd()))->run();
            (Process::fromShellCommandline('sed -i "" "/character_set_client/d" ./dumps/dev/latest.sql', getcwd()))->run();

            // remove temporary files
            (new Process(['rm', '-f', './dumps/dev/concat.sql'], getcwd()))->run();
            (Process::fromShellCommandline('rm -f ./dumps/dev/temp*.sql', getcwd()))->run();
        } catch (\Throwable $throwable) {
            render(<<<HTML
                <span>{$throwable->getMessage()}</span>
            HTML);

            return Command::FAILURE;
        }

        render(<<<'HTML'
            <div class="py-1 ml-2">
                <span>Database dump was successful.</span>
            </div>
        HTML);

        return Command::SUCCESS;
    }

    private function getDumper(array $pdoOptions): Mysqldump
    {
        preg_match('/^mysql:\/\/'.
            '(?:(?<username>.*)'.
            '(?::(?<password>.*))@)'.
            '(?<host>.*)'.
            '\/(?<dbname>.*)'.
        '$/', $_ENV['PIMCORE_DB_DSN'], $dsn);

        return new Mysqldump(
            "mysql:host={$dsn['host']};dbname={$dsn['dbname']}",
            $dsn['username'],
            $dsn['password'],
            $pdoOptions,
        );
    }
}
