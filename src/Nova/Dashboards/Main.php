<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Nova\Dashboards;

use DigitalCreative\NovaWelcomeCard\WelcomeCard;
use Hapheus\NovaSingleValueCard\NovaSingleValueCard;
use Laravel\Nova\Dashboards\Main as Dashboard;
use Movecloser\ProcessManager\Console\Commands\ProcessManager;
use Movecloser\ProcessManager\Lockdown\CommandLock;
use Movecloser\ProcessManager\Lockdown\CommandsStatus;
use Movecloser\ProcessManager\Nova\Metrics\AvgProcessAttempts;
use Movecloser\ProcessManager\Nova\Metrics\NewProcesses;

class Main extends Dashboard
{
    protected const array COMMANDS = [];

    public function cards(): array
    {
        return array_merge(
            [
                AvgProcessAttempts::make()->defaultRange(30)->width('1/2'),
                NewProcesses::make()->defaultRange(30)->width('1/2'),
                new NovaSingleValueCard('All commands', CommandLock::allCommandsDisabled() ? 'DISABLED' : 'Enabled'),
                new NovaSingleValueCard('Process Manager', CommandsStatus::checkCommandStatus(ProcessManager::lockKey())),
            ],
            [
                ...$this->commands(),
            ]
        );
    }

    protected function commands(): array
    {
        if (empty(static::COMMANDS)) {
            return [];
        }

        $card = WelcomeCard::make()
            ->title('Commands status');

        foreach (static::COMMANDS as $command => $title) {
            if (!$title) {
                $card->addItem('', '', '');
                continue;
            }

            if (is_array($title)) {
                $params = $title[1];
                $title = $title[0];
                foreach ($params as $param) {
                    $this->addCard($card, $command, $title, (string) $param);
                }
            } else {
                $this->addCard($card, $command, $title);
            }
        }

        return [$card];
    }

    private function addCard(WelcomeCard $card, string $command, string $title, ?string $param = null): void
    {
        $lockKey = $command::lockKey($param);
        $status = CommandsStatus::checkCommandStatus($lockKey);
        $meta = match ($status) {
            CommandsStatus::COMMAND_STATUS_IDLE => ['shield-check', 'green'],
            CommandsStatus::COMMAND_STATUS_WORKING => ['cog', 'green'],
            CommandsStatus::COMMAND_STATUS_DISABLED => ['exclamation-circle', 'orange'],
            CommandsStatus::COMMAND_STATUS_LOCKED => ['exclamation', 'red'],
            CommandsStatus::COMMAND_STATUS_ERROR => ['exclamation-circle', 'red'],
        };

        $errors = CommandLock::getError($lockKey);
        if (!empty($errors)) {
            $errors = '<br/>' . nl2br($errors);
        }

        $card->addItem(
            icon: $meta[0],
            title: $title . (!empty($param) ? ' (' . $param . ')' : ''),
            content: sprintf('<div style="word-break: break-all"><strong style="color: %s;">%s</strong>%s</div>', $meta[1], $status, $errors)
        );
    }
}
