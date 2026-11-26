<?php
declare(strict_types=1);

namespace Apicore\Services;

use Apicore\Core\Settings;
use RuntimeException;
use Throwable;

/**
 * Real email delivery over SMTP (Gmail, Outlook or any SMTP server).
 *
 * Every email of the store is first saved in dbo.email_outbox (the admin panel
 * shows it in "Correos"). When MAIL_HOST is set in api/v3/.env the email is also
 * sent to the customer's inbox. Without MAIL_HOST nothing is sent and the store
 * keeps working: the outbox is the simulated mailbox.
 *
 * .env keys:
 *   MAIL_HOST        smtp.gmail.com
 *   MAIL_PORT        465 (ssl) or 587 (tls)
 *   MAIL_ENCRYPTION  ssl | tls | none
 *   MAIL_USER        account that sends (for Gmail, the full address)
 *   MAIL_PASSWORD    for Gmail an "app password" (16 letters), never the normal one
 *   MAIL_FROM        sender address (defaults to MAIL_USER)
 *   MAIL_FROM_NAME   sender name (defaults to "JAM Sport")
 *
 * Layer: services. No HTTP; NotificationService calls it after saving the email.
 */
final class MailService
{
    private const TIMEOUT_SECONDS = 15;

    /** @var resource|null */
    private $socket = null;

    /** True when the server has SMTP configured. */
    public static function enabled(): bool
    {
        return trim((string) Settings::instance()->get('MAIL_HOST', '')) !== '';
    }

    /**
     * Send without interrupting the operation that triggered it: a mail server
     * that is down must not cancel a registration or an order.
     */
    public static function sendSafely(string $to, string $subject, string $text, ?string $link): bool
    {
        if (!self::enabled()) {
            return false;
        }
        try {
            $settings = Settings::instance();
            (new self())->send(
                (string) $settings->get('MAIL_HOST'),
                (int) $settings->get('MAIL_PORT', '465'),
                strtolower((string) $settings->get('MAIL_ENCRYPTION', 'ssl')),
                (string) $settings->get('MAIL_USER', ''),
                (string) $settings->get('MAIL_PASSWORD', ''),
                (string) $settings->get('MAIL_FROM', (string) $settings->get('MAIL_USER', '')),
                (string) $settings->get('MAIL_FROM_NAME', 'JAM Sport'),
                $to,
                $subject,
                self::html($subject, $text, $link),
                $text . ($link ? "\r\n\r\n" . $link : ''),
            );
            return true;
        } catch (Throwable $e) {
            error_log('No se pudo enviar el correo a ' . $to . ': ' . $e->getMessage());
            return false;
        }
    }

    /** One SMTP conversation: connect, authenticate, send one message and quit. */
    public function send(
        string $host,
        int $port,
        string $encryption,
        string $user,
        string $password,
        string $from,
        string $fromName,
        string $to,
        string $subject,
        string $html,
        string $text,
    ): void {
        foreach ([$from, $to] as $address) {
            if (!filter_var($address, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Dirección de correo no válida: ' . $address);
            }
        }
        $remote = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $context = stream_context_create(['ssl' => ['peer_name' => $host, 'SNI_enabled' => true]]);
        $socket = @stream_socket_client($remote, $errno, $error, self::TIMEOUT_SECONDS, STREAM_CLIENT_CONNECT, $context);
        if ($socket === false) {
            throw new RuntimeException('No hay conexión con el servidor de correo (' . $error . ').');
        }
        $this->socket = $socket;
        stream_set_timeout($socket, self::TIMEOUT_SECONDS);
        try {
            $this->expect(220);
            $this->command('EHLO jamsport.local', 250);
            if ($encryption === 'tls') {
                $this->command('STARTTLS', 220);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('No se pudo iniciar TLS con el servidor de correo.');
                }
                $this->command('EHLO jamsport.local', 250);
            }
            if ($user !== '') {
                $this->command('AUTH LOGIN', 334);
                $this->command(base64_encode($user), 334);
                $this->command(base64_encode($password), 235);
            }
            $this->command('MAIL FROM:<' . $from . '>', 250);
            $this->command('RCPT TO:<' . $to . '>', [250, 251]);
            $this->command('DATA', 354);
            // A line that starts with a dot gets one more, so it is not read as the end of the data
            $this->write(preg_replace('/^\./m', '..', self::message($from, $fromName, $to, $subject, $html, $text)));
            $this->command('.', 250);
            $this->command('QUIT', 221);
        } finally {
            fclose($socket);
            $this->socket = null;
        }
    }

    /** MIME message with a plain text part and an HTML part. */
    public static function message(string $from, string $fromName, string $to, string $subject, string $html, string $text): string
    {
        $boundary = 'jam-' . bin2hex(random_bytes(12));
        $headers = [
            'Date: ' . date('r'),
            'From: ' . self::encodeHeader($fromName) . ' <' . $from . '>',
            'To: <' . $to . '>',
            'Subject: ' . self::encodeHeader($subject),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@jamsport.local>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];
        $body = '--' . $boundary . "\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($text)) . '--' . $boundary . "\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html)) . '--' . $boundary . '--';
        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }

    /** Simple branded HTML body: text, and a button when there is a link. */
    public static function html(string $subject, string $text, ?string $link): string
    {
        $esc = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $button = $link
            ? '<p style="margin:28px 0"><a href="' . $esc($link) . '" style="background:#111;color:#fff;padding:14px 22px;border-radius:8px;text-decoration:none;font-weight:bold">Abrir en JAM Sport</a></p>'
              . '<p style="color:#666;font-size:12px">Si el botón no funciona, copia esta dirección en tu navegador:<br>' . $esc($link) . '</p>'
            : '';
        return '<!DOCTYPE html><html lang="es"><body style="margin:0;background:#f4f4f5;font-family:Arial,sans-serif">'
            . '<div style="max-width:560px;margin:0 auto;padding:24px">'
            . '<div style="background:#111;color:#fff;padding:18px 24px;border-radius:12px 12px 0 0;font-size:20px;font-weight:bold">JAM Sport</div>'
            . '<div style="background:#fff;padding:24px;border-radius:0 0 12px 12px;color:#111;line-height:1.5">'
            . '<h1 style="font-size:20px;margin:0 0 12px">' . $esc($subject) . '</h1>'
            . '<p>' . nl2br($esc($text)) . '</p>' . $button
            . '</div><p style="color:#888;font-size:11px;text-align:center">Correo automático de JAM Sport. No respondas a este mensaje.</p>'
            . '</div></body></html>';
    }

    private static function encodeHeader(string $value): string
    {
        return preg_match('/[^\x20-\x7E]/', $value) ? '=?UTF-8?B?' . base64_encode($value) . '?=' : $value;
    }

    /** @param int|list<int> $expected */
    private function command(string $line, int|array $expected): void
    {
        $this->write($line);
        $this->expect($expected);
    }

    private function write(string $data): void
    {
        if (fwrite($this->socket, $data . "\r\n") === false) {
            throw new RuntimeException('Se perdió la conexión con el servidor de correo.');
        }
    }

    /** @param int|list<int> $expected */
    private function expect(int|array $expected): string
    {
        $reply = '';
        while (($line = fgets($this->socket, 1024)) !== false) {
            $reply .= $line;
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }
        $code = (int) substr($reply, 0, 3);
        if (!in_array($code, (array) $expected, true)) {
            throw new RuntimeException('El servidor de correo respondió: ' . trim($reply));
        }
        return $reply;
    }
}
