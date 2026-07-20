<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TwoFactorCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $code) {}

    public function build(): self
    {
        return $this->subject('Votre code de connexion — Radio CR Manager')
            ->view('emails.two-factor-code');
    }
}
