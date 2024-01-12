<?php

namespace Unleash\Client\Bundle\Command;

use LogicException;
use Psr\Cache\CacheItemPoolInterface;
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
    public function __construct(
        string $name,
        private readonly Unleash $unleash,
        private readonly CacheItemPoolInterface $cache,
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                name: 'flag',
                mode: InputArgument::REQUIRED,
                description: 'The name of the feature flag to check the result for',
            )
            ->addOption(
                name: 'force',
                shortcut: 'f',
                mode: InputOption::VALUE_NONE,
                description: 'When this flag is present, fresh results without cache will be forced',
            )
            ->addOption(
                name: 'user-id',
                mode: InputOption::VALUE_REQUIRED,
                description: "[Context] Provide the current user's ID",
                default: null,
            )
            ->addOption(
                name: 'ip-address',
                mode: InputOption::VALUE_REQUIRED,
                description: '[Context] Provide the current IP address',
                default: null,
            )
            ->addOption(
                name: 'session-id',
                mode: InputOption::VALUE_REQUIRED,
                description: '[Context] Provide the current session ID',
                default: null,
            )
            ->addOption(
                name: 'hostname',
                mode: InputOption::VALUE_REQUIRED,
                description: '[Context] Provide the current hostname',
                default: null,
            )
            ->addOption(
                name: 'environment',
                mode: InputOption::VALUE_REQUIRED,
                description: '[Context] Provide the current environment',
                default: null,
            )
            ->addOption(
                name: 'current-time',
                mode: InputOption::VALUE_REQUIRED,
                description: '[Context] Provide the current date and time',
                default: null,
            )
            ->addOption(
                'custom-context',
                mode: InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                description: '[Context] Custom context values in the format [contextName]=[contextValue], for example: myCustomContextField=someValue',
                default: null,
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
        if ($input->getOption('force')) {
            $this->cache->clear();
        }

        $result = $this->unleash->isEnabled(
            $input->getArgument('flag'),
            $this->createContext($input),
        );

        $expected = $input->getOption('expected');
        if ($expected !== null) {
            $expected = $expected === 'true';
        }
        $success = ($expected === null && $result) || ($expected !== null && $result === $expected);
        $message = "The feature flag '{$input->getArgument('flag')}' evaluated to: " . ($result ? 'true' : 'false');

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
        $customContext = [];
        foreach ($input->getOption('custom-context') as $item) {
            if (!fnmatch('*=*', $item)) {
                throw new LogicException('The value must be a key=value pair.');
            }
            [$key, $value] = explode('=', $item);
            $customContext[trim($key)] = trim($value);
        }

        return new UnleashContext(
            currentUserId: $input->getOption('user-id'),
            ipAddress: $input->getOption('ip-address'),
            sessionId: $input->getOption('session-id'),
            customContext: $customContext,
            hostname: $input->getOption('hostname'),
            environment: $input->getOption('environment'),
            currentTime: $input->getOption('current-time'),
        );
    }
}
