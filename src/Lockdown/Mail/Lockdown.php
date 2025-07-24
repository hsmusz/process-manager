<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Lockdown\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class Lockdown extends Mailable
{
    use SerializesModels;

    protected array $details;
    protected string $msg;

    public function __construct(string $msg, array $details, string $subject)
    {
        $this->msg = $msg;
        $this->subject = $subject;
        $this->details = $details;
    }

    public function build(): self
    {
        $this->subject($this->subject)
            ->view('process-manager::mails.lockdown', [
                'msg' => $this->msg,
                'details' => $this->details,
            ]);

        return $this;
    }
}
