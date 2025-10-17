<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Lockdown\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class Lockdown extends Mailable
{
    use SerializesModels;

    public const string LOCKDOWN_TYPE_SOFTLY = 'softly';
    public const string LOCKDOWN_TYPE_HARD = 'hard';

    protected array $details;
    protected string $msg;
    protected string $type;

    public function __construct(string $subject, string $msg, string $type, array $details)
    {
        $this->subject = $subject;
        $this->msg = $msg;
        $this->type = $type;
        $this->details = $details;
    }

    public function build(): self
    {
        $this->subject($this->subject)
            ->view('process-manager::mails.lockdown', [
                'msg' => $this->msg,
                'heading' => $this->type === self::LOCKDOWN_TYPE_SOFTLY ? 'Soft Lockdown' : 'LOCKDOWN!',
                'details' => $this->details,
            ]);

        return $this;
    }
}
