<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Nova\Dashboards;

use DigitalCreative\NovaWelcomeCard\WelcomeCard;
use Hapheus\NovaSingleValueCard\NovaSingleValueCard;
use Ideatocode\NovaSpacerCard\NovaSpacerCard;
use Laravel\Nova\Dashboards\Main as Dashboard;
use Movecloser\ProcessManager\Console\Commands\ProcessManager;
use Movecloser\ProcessManager\Lockdown\CommandLock;
use Movecloser\ProcessManager\Lockdown\CommandStatusResolver;
use Movecloser\ProcessManager\Nova\Metrics\MaxProcessAttempts;
use Movecloser\ProcessManager\Nova\Metrics\NewProcesses;

class Main extends Dashboard
{
    private const string HR_LINE = '<hr style="margin: 10px 0" />';

    public function cards(): array
    {
        return array_merge(
            [
                MaxProcessAttempts::make()->defaultRange(30)->width('1/2'),
                NewProcesses::make()->defaultRange(30)->width('1/2'),
            ],
            [
                (new NovaSpacerCard())->width('full')->classes('bg-gray-100')->style('height: .5rem'),
                (new NovaSingleValueCard('All commands', CommandLock::allCommandsDisabled() ? 'DISABLED' : 'Enabled'))->width('1/4'),
                new NovaSingleValueCard('Process Manager', CommandStatusResolver::checkCommandStatus(ProcessManager::lockKey('default'))),
                new NovaSingleValueCard('Process Manager - TCPOS', CommandStatusResolver::checkCommandStatus(ProcessManager::lockKey('tcpos'))),
            ],
            [
                ...$this->commands(),
            ]
        );
    }

    protected function commands(): array
    {
        $commands = CommandStatusResolver::commands();
        if (empty($commands)) {
            return [];
        }

        $card = WelcomeCard::make()
            ->title('Commands status');

        foreach ($commands as $command => $title) {
            if (!$title) {
                $card->addItem('', '', '');
                continue;
            }

            if (is_array($title)) {
                [$title, $params] = $title;
            } else {
                $params = [null];
            }

            foreach ($params as $param) {
                $this->addCard($card, $command, $title, $param);
            }
        }

        return [$card];
    }

    private function addCard(WelcomeCard $card, string $command, string $title, mixed $param = null): void
    {
        if (!empty($param)) {
            $param = (string) $param;
        }

        $lockKey = $command::lockKey($param);
        $status = CommandStatusResolver::checkCommandStatus($lockKey);
        $meta = match ($status) {
            // use https://v1.heroicons.com/
            CommandStatusResolver::COMMAND_STATUS_IDLE => ['template', 'green'],
            CommandStatusResolver::COMMAND_STATUS_WORKING => ['cog', 'green'],
            CommandStatusResolver::COMMAND_STATUS_DISABLED => ['exclamation-circle', 'orange'],
            CommandStatusResolver::COMMAND_STATUS_LOCKED => ['exclamation', 'red'],
            CommandStatusResolver::COMMAND_STATUS_ERROR => ['exclamation-circle', 'red'],
        };

        $errors = CommandLock::getError($lockKey);
        if (!empty($errors)) {
            $errors = self::HR_LINE . str_replace("\n", self::HR_LINE, $errors);
        }

        $last = CommandLock::lastExecutionDate($lockKey);
        if (!empty($last)) {
            $last = '<div class="font-size: 10px;">last execution: ' . $last . '</div>';
        }

        $card->addItem(
            icon: $meta[0],
            title: $title . (!empty($param) ? ' (' . $param . ')' : ''),
            content: $last . sprintf('<div style="word-break: break-all"><strong style="color: %s;">%s</strong>%s</div>', $meta[1], $status, $errors)
        );
    }
}
