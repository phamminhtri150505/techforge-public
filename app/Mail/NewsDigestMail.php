<?php

namespace App\Mail;

use App\Models\NewsDigest;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class NewsDigestMail extends Mailable
{
    public function __construct(public NewsDigest $digest) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'TECHFORGE · Tin công nghệ mới');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.news-digest');
    }
}
