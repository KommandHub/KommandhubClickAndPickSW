<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Tests;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\TestBootstrapper as ShopwareTestBootstrapper;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpKernel\KernelInterface;

class TestBootstrapper extends ShopwareTestBootstrapper
{
    private bool $forceInstallPlugins = false;

    private bool $loadEnvFile = true;

    /**
     * @var array<string>
     */
    private array $activePlugins = [];

    public function bootstrap(): ShopwareTestBootstrapper
    {
        $_SERVER['PROJECT_ROOT'] = $_ENV['PROJECT_ROOT'] = $this->getProjectDir();

        if (!\defined('TEST_PROJECT_DIR')) {
            \define('TEST_PROJECT_DIR', $_SERVER['PROJECT_ROOT']);
        }

        $classLoader = $this->getClassLoader();

        if ($this->loadEnvFile) {
            $this->loadEnvFile();
        }

        $_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = $this->getDatabaseUrl();

        KernelLifecycleManager::prepare($classLoader);

        if ($this->isForceInstall() || !$this->dbExists()) {
            $this->install();

            if ($this->activePlugins !== []) {
                $this->installPlugins();
            }
        } elseif ($this->forceInstallPlugins) {
            $this->installPlugins();
        }

        return $this;
    }

    private function getKernel(): KernelInterface
    {
        return KernelLifecycleManager::getKernel();
    }

    private function getKernelContainer(): ContainerInterface
    {
        return $this->getKernel()->getContainer();
    }

    private function dbExists(): bool
    {
        try {
            $connection = $this->getKernelContainer()->get(Connection::class);
            $connection->executeQuery('SELECT 1 FROM `plugin`')->fetchAllAssociative();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function loadEnvFile(): void
    {
        if (!class_exists(Dotenv::class)) {
            throw new \RuntimeException('APP_ENV environment variable is not defined. You need to define environment variables for configuration or add "symfony/dotenv" as a Composer dependency to load variables from a .env file.');
        }

        $envFilePath = $this->getProjectDir() . '/.env';

        if (\is_file($envFilePath) || \is_file($envFilePath . '.dist') || \is_file($envFilePath . '.local.php')) {
            (new Dotenv())->usePutenv()->bootEnv($envFilePath);
        }
    }

    private function install(): void
    {
        $application = new Application($this->getKernel());

        $returnCode = $application->doRun(
            new ArrayInput([
                'command' => 'system:install',
                '--create-database' => true,
                '--force' => true,
                '--drop-database' => true,
                '--basic-setup' => true,
                '--no-assign-theme' => true,
            ]),
            $this->getOutput()
        );

        if ($returnCode !== Command::SUCCESS) {
            throw new \RuntimeException('system:install failed');
        }

        // create new kernel after install
        KernelLifecycleManager::bootKernel(false);
    }

    private function installPlugins(): void
    {
        $application = new Application($this->getKernel());
        $application->doRun(new ArrayInput(['command' => 'plugin:refresh']), $this->getOutput());

        $kernel = KernelLifecycleManager::bootKernel();
        $application = new Application($kernel);

        foreach ($this->activePlugins as $activePlugin) {
            $returnCode = $application->doRun(
                new ArrayInput([
                    'command' => 'plugin:install',
                    '--activate' => true,
                    '--reinstall' => true,
                    'plugins' => [$activePlugin],
                ]),
                $this->getOutput()
            );

            if ($returnCode !== Command::SUCCESS) {
                throw new \RuntimeException('plugin:install failed');
            }
        }

        KernelLifecycleManager::bootKernel();
    }
}
