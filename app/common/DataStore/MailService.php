<?php
declare(strict_types=1);

namespace App\Common\DataStore;

/**
 * Class MailService
 * @package App\Common\DataStore
 */
enum MailService: string
{
    case DISABLED = "disabled";
    case SMTP = "smtp";
    case MAILGUN = "mailgun";
    case SENDGRID = "sendgrid";
}
