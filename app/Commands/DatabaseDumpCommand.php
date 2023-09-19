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

        $processBinary = $binDir.DIRECTORY_SEPARATOR.'process-mysqldump';

        try {
            $dotenv = new Dotenv();
            $dotenv->loadEnv(getcwd().'/.env');
        } catch (PathException $exception) {
            $this->fail($exception->getMessage());
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

            if (! file_exists('dumps/dev')) {
                mkdir('dumps/dev', 0777, true);
            }

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
            (new Process(['rm', '-f', './dumps/dev/latest.sql'], getcwd()))->mustRun();

            // concat dumps
            (Process::fromShellCommandline('cat ./dumps/dev/temp*.sql >> ./dumps/dev/concat.sql', getcwd()))->mustRun();

            // format insert statements with process-mysqldump
            (Process::fromShellCommandline("$processBinary ./dumps/dev/concat.sql >> ./dumps/dev/latest.sql", getcwd()))->mustRun();

            // remove unwanted lines
            (Process::fromShellCommandline('sed -i "" "/@OLD_UNIQUE_CHECKS/d" ./dumps/dev/latest.sql', getcwd()))->mustRun();
            (Process::fromShellCommandline('sed -i "" "/character_set_client/d" ./dumps/dev/latest.sql', getcwd()))->mustRun();

            // remove temporary files
            (new Process(['rm', '-f', './dumps/dev/concat.sql'], getcwd()))->mustRun();
            (Process::fromShellCommandline('rm -f ./dumps/dev/temp*.sql', getcwd()))->mustRun();
        } catch (\Throwable $throwable) {
            $this->fail($throwable->getMessage());
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

    private function fail(string $message): int
    {
        render(<<<HTML
            <div class="py-1 ml-2">
            <span>Error: {$message}</span>
            </div>
        HTML);

        exit(Command::FAILURE);
    }
}
