<?php

namespace Unleash\Client\Bundle\Command;

use LogicException;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Unleash\Client\Configuration\Context;
use Unleash\Client\Configuration\UnleashContext;
use Unleash\Client\Unleash;

final class TestFlagCommand extends Command
{
    /**
     * @readonly
     * @var \Unleash\Client\Unleash
     */
    private $unleash;
    /**
     * @readonly
     * @var \Psr\SimpleCache\CacheInterface
     */
    private $cache;
    public function __construct(string $name, Unleash $unleash, CacheInterface $cache)
    {
        $this->unleash = $unleash;
        $this->cache = $cache;
        parent::__construct($name);
    }
    protected function configure(): void
    {
        $this
            ->setDescription('Check the status of an Unleash feature')
            ->addArgument(
                'flag',
                InputArgument::REQUIRED,
                'The name of the feature flag to check the result for',
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'When this flag is present, fresh results without cache will be forced',
            )
            ->addOption(
                'user-id',
                null,
                InputOption::VALUE_REQUIRED,
                "[Context] Provide the current user's ID",
                null,
            )
            ->addOption(
                'ip-address',
                null,
                InputOption::VALUE_REQUIRED,
                '[Context] Provide the current IP address',
                null,
            )
            ->addOption(
                'session-id',
                null,
                InputOption::VALUE_REQUIRED,
                '[Context] Provide the current session ID',
                null,
            )
            ->addOption(
                'hostname',
                null,
                InputOption::VALUE_REQUIRED,
                '[Context] Provide the current hostname',
                null,
            )
            ->addOption(
                'environment',
                null,
                InputOption::VALUE_REQUIRED,
                '[Context] Provide the current environment',
                null,
            )
            ->addOption(
                'current-time',
                null,
                InputOption::VALUE_REQUIRED,
                '[Context] Provide the current date and time',
                null,
            )
            ->addOption(
                'custom-context',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                '[Context] Custom context values in the format [contextName]=[contextValue], for example: myCustomContextField=someValue',
                null,
            )
            ->addOption( // must use positional arguments, because $suggestedValues is not a real argument
                'expected',
                null,
                InputOption::VALUE_REQUIRED,
                'For use in testing, if this option is present, the exit code will be either 0 or 1 depending on whether the expectation matches the result',
                null,
                ['true', 'false'], // suggested values
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $flagName = $input->getArgument('flag');
        assert(is_string($flagName));

        if ($input->getOption('force')) {
            $this->cache->clear();
        }

        $result = $this->unleash->isEnabled(
            $flagName,
            $this->createContext($input),
        );

        $expected = $input->getOption('expected');
        if ($expected !== null) {
            $expected = $expected === 'true';
        }
        $success = ($expected === null && $result) || ($expected !== null && $result === $expected);
        $message = "The feature flag '{$flagName}' evaluated to: " . ($result ? 'true' : 'false');

        $success
            ? $io->success($message)
            : $io->error($message)
        ;

        return $expected === null
            ? Command::SUCCESS
            : (
                $result === $expected
                    ? Command::SUCCESS
                    : Command::FAILURE
            )
        ;
    }

    private function createContext(InputInterface $input): Context
    {
        $customContextInput = $input->getOption('custom-context');
        assert(is_array($customContextInput));

        $customContext = [];
        foreach ($customContextInput as $item) {
            if (!fnmatch('*=*', $item)) {
                throw new LogicException('The value must be a key=value pair.');
            }
            [$key, $value] = explode('=', $item);
            $customContext[trim($key)] = trim($value);
        }

        $userId = $input->getOption('user-id');
        $ipAddress = $input->getOption('ip-address');
        $sessionId = $input->getOption('session-id');
        $hostname = $input->getOption('hostname');
        $environment = $input->getOption('environment');
        $currentTime = $input->getOption('current-time');

        assert($userId === null || is_string($userId));
        assert($ipAddress === null || is_string($ipAddress));
        assert($sessionId === null || is_string($sessionId));
        assert($hostname === null || is_string($hostname));
        assert($environment === null || is_string($environment));
        assert($currentTime === null || is_string($currentTime));

        return new UnleashContext(
            $userId,
            $ipAddress,
            $sessionId,
            $customContext,
            $hostname,
            $environment,
            $currentTime,
        );
    }
}
