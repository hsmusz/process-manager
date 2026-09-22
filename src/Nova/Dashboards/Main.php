<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Nova\Dashboards;

use Carbon\Carbon;
use DigitalCreative\NovaWelcomeCard\WelcomeCard;
use Hapheus\NovaSingleValueCard\NovaSingleValueCard;
use Ideatocode\NovaSpacerCard\NovaSpacerCard;
use Laravel\Nova\Dashboards\Main as Dashboard;
use Movecloser\ProcessManager\Console\Commands\ProcessManager;
use Movecloser\ProcessManager\Lockdown\CommandLock;
use Movecloser\ProcessManager\Lockdown\CommandStatusResolver;
use Movecloser\ProcessManager\Lockdown\GlobalLock;
use Movecloser\ProcessManager\Nova\Metrics\MaxProcessAttempts;
use Movecloser\ProcessManager\Nova\Metrics\NewProcesses;
use Movecloser\ProcessManager\Support\ErrorMessage;

class Main extends Dashboard
{
    private const string STYLE_BADGE = 'display:inline-block;padding:1px 8px;border-radius:9999px;background:%s;color:%s;font-size:11px;font-weight:700;letter-spacing:.04em';
    private const string STYLE_ERROR_BOX = 'margin-top:8px;padding:6px 9px;border-left:3px solid #dc2626;border-radius:3px;background:rgba(220,38,38,.09);font-size:12px;line-height:1.5';
    private const string STYLE_GRID = 'display:grid;grid-template-columns:auto minmax(0,1fr);gap:3px 10px';
    private const string STYLE_LABEL = 'font-size:10px;letter-spacing:.08em;text-transform:uppercase;opacity:.5';
    private const string STYLE_MESSAGE_CLAMP = 'display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden';
    private const string STYLE_TIME = 'white-space:nowrap;font-variant-numeric:tabular-nums';

    public function cards(): array
    {
        $cards = [
            (new NovaSpacerCard())->width('full')->classes('bg-gray-100')->style('height: .5rem'),
            (new NovaSingleValueCard('All commands', GlobalLock::allDisabled() ? 'DISABLED' : 'Enabled'))->width('1/4'),
        ];

        $channels = config('process-manager.channels', ['default' => 'Process Manager']);

        foreach ($channels as $channel => $name) {
            $cards[] = new NovaSingleValueCard($name, CommandStatusResolver::checkCommandStatus(ProcessManager::lockKey($channel)));
        }

        return array_merge(
            [
                MaxProcessAttempts::make()->defaultRange(30)->width('1/2'),
                NewProcesses::make()->defaultRange(30)->width('1/2'),
            ],
            $cards,
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
            // use https://v1.heroicons.com/ | [ikona, tło badge, tekst badge]
            CommandStatusResolver::COMMAND_STATUS_IDLE => ['template', '#dcfce7', '#166534'],
            CommandStatusResolver::COMMAND_STATUS_WORKING => ['cog', '#dbeafe', '#1e40af'],
            CommandStatusResolver::COMMAND_STATUS_DISABLED => ['exclamation-circle', '#ffedd5', '#9a3412'],
            CommandStatusResolver::COMMAND_STATUS_LOCKED => ['exclamation', '#ffe4e6', '#9f1239'],
            CommandStatusResolver::COMMAND_STATUS_ERROR => ['exclamation-circle', '#fee2e2', '#991b1b'],
        };

        $lastExecution = CommandLock::lastExecution($lockKey);
        $errors = CommandLock::errorLog($lockKey);

        $content = sprintf('<span style="' . self::STYLE_BADGE . '">%s</span>', $meta[1], $meta[2], $status);

        if ($lastExecution) {
            $content .= sprintf(
                '<span style="margin-left:10px;font-size:12px;opacity:.6;%s">%s</span>',
                self::STYLE_TIME,
                $lastExecution->format('Y-m-d H:i:s')
            );
        }

        $card->addItem(
            icon: $meta[0],
            title: $title . (!empty($param) ? ' (' . $param . ')' : ''),
            content: sprintf(
                '<div style="overflow-wrap:anywhere">%s%s%s</div>',
                $content,
                $this->currentErrors($errors['current'], $lastExecution),
                $this->previousErrors($errors['previous'], $lastExecution)
            )
        );
    }

    /**
     * @param array<int, array{at: ?\Carbon\Carbon, message: string}> $entries
     */
    private function currentErrors(array $entries, ?Carbon $lastExecution): string
    {
        if (empty($entries)) {
            return '';
        }

        return sprintf(
            '<div style="%s;%s">%s</div>',
            self::STYLE_ERROR_BOX,
            self::STYLE_GRID,
            $this->errorRows($entries, $lastExecution, '.6', false)
        );
    }

    /**
     * @param array<int, array{at: ?\Carbon\Carbon, message: string}> $entries
     */
    private function errorRows(array $entries, ?Carbon $lastExecution, string $timeOpacity, bool $clamp): string
    {
        $rows = '';

        foreach ($entries as $entry) {
            $message = e(ErrorMessage::plain($entry['message']));

            $rows .= sprintf(
                '<span style="%s;opacity:%s">%s</span><span style="%s"%s>%s</span>',
                self::STYLE_TIME,
                $timeOpacity,
                $this->errorTime($entry['at'], $lastExecution),
                $clamp ? self::STYLE_MESSAGE_CLAMP : '',
                $clamp ? sprintf(' title="%s"', $message) : '',
                $message
            );
        }

        return $rows;
    }

    private function errorTime(?Carbon $at, ?Carbon $lastExecution): string
    {
        if (!$at) {
            return '-';
        }

        return $at->isSameDay($lastExecution ?? Carbon::now()) ? $at->format('H:i:s') : $at->format('m-d H:i');
    }

    /**
     * @param array<int, array{at: ?\Carbon\Carbon, message: string}> $entries
     */
    private function previousErrors(array $entries, ?Carbon $lastExecution): string
    {
        if (empty($entries)) {
            return '';
        }

        return sprintf(
            '<div style="margin-top:12px;padding-top:8px;border-top:1px solid rgba(127,127,127,.3)">'
            . '<div style="%s">Previous errors (%d)</div>'
            . '<div style="%s;margin-top:4px;font-size:12px;line-height:1.4;opacity:.7">%s</div></div>',
            self::STYLE_LABEL,
            count($entries),
            self::STYLE_GRID,
            $this->errorRows($entries, $lastExecution, '.75', true)
        );
    }
}
