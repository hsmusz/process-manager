<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Nova\Dashboards;

use DigitalCreative\NovaWelcomeCard\WelcomeCard;
use Hapheus\NovaSingleValueCard\NovaSingleValueCard;
use Laravel\Nova\Dashboards\Main as Dashboard;
use Movecloser\ProcessManager\Console\Commands\ProcessManager;
use Movecloser\ProcessManager\Lockdown\CommandLock;
use Movecloser\ProcessManager\Lockdown\CommandsStatus;

class Main extends Dashboard
{
    protected const array COMMANDS = [];

    public function cards(): array
    {
        return array_merge(
            [
                new NovaSingleValueCard('All commands', CommandLock::allCommandsDisabled() ? 'DISABLED' : 'Enabled'),
                new NovaSingleValueCard('Process Manager', CommandsStatus::checkCommandStatus(ProcessManager::class)),
            ],
            [
                ...$this->commands(),
            ]
        );
    }

    protected function commands(): array
    {
        if(empty(static::COMMANDS)) {
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
        $status = CommandsStatus::checkCommandStatus($command, $param);
        $meta = match ($status) {
            CommandsStatus::COMMAND_STATUS_IDLE => ['shield-check', 'green'],
            CommandsStatus::COMMAND_STATUS_WORKING => ['cog', 'green'],
            CommandsStatus::COMMAND_STATUS_DISABLED => ['exclamation-circle', 'orange'],
            CommandsStatus::COMMAND_STATUS_LOCKED => ['exclamation', 'red'],
            CommandsStatus::COMMAND_STATUS_ERROR => ['exclamation-circle', 'red'],
        };
        $card->addItem(
            icon: $meta[0],
            title: $title . (!empty($param) ? ' (' . $param . ')' : ''),
            content: sprintf('<strong style="color: %s;">%s</strong>', $meta[1], $status)
        );
    }
}
