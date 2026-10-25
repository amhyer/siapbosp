<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Handler\SocketHandler;
use Monolog\Formatter\JsonFormatter;
use Monolog\Processor\PsrLogMessageProcessor;

$auditChannels = ['audit_file'];
$auditRemoteChannel = env('AUDIT_REMOTE_CHANNEL');
if ($auditRemoteChannel === 'syslog') {
    $auditChannels[] = 'audit_syslog';
}
$auditRemoteUrl = (string) env('AUDIT_REMOTE_URL', '');
$auditRemoteParts = parse_url($auditRemoteUrl);
$auditRemoteTlsValid = is_array($auditRemoteParts)
    && ($auditRemoteParts['scheme'] ?? null) === 'tls'
    && ! empty($auditRemoteParts['host'])
    && isset($auditRemoteParts['port'])
    && $auditRemoteParts['port'] >= 1
    && $auditRemoteParts['port'] <= 65535
    && ! isset($auditRemoteParts['user'])
    && ! isset($auditRemoteParts['pass'])
    && ! isset($auditRemoteParts['path'])
    && ! isset($auditRemoteParts['query'])
    && ! isset($auditRemoteParts['fragment']);
if ($auditRemoteChannel === 'socket' && $auditRemoteTlsValid) {
    $auditChannels[] = 'audit_remote_socket';
}

return [

    // Configuration status for deployment-time warnings; never contains a secret.
    'audit_remote_channel' => $auditRemoteChannel,
    'audit_remote_tls_valid' => $auditRemoteTlsValid,

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that is utilized to write
    | messages to your logs. The value provided here should match one of
    | the channels present in the list of "channels" configured below.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    |
    | This option controls the log channel that should be used to log warnings
    | regarding deprecated PHP and library features. This allows you to get
    | your application ready for upcoming major versions of dependencies.
    |
    */

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => env('LOG_DEPRECATIONS_TRACE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Laravel
    | utilizes the Monolog PHP logging library, which includes a variety
    | of powerful log handlers and formatters that you're free to use.
    |
    | Available drivers: "single", "daily", "monthly", "slack", "syslog",
    |                    "errorlog", "monolog", "custom", "stack"
    |
    */

    'channels' => [

        'stack' => [
            'driver' => 'stack',
            'channels' => explode(',', (string) env('LOG_STACK', 'single')),
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'max_files' => env('LOG_DAILY_DAYS', 14),
            'replace_placeholders' => true,
        ],

        // Salinan lokal selalu dipertahankan. Remote dapat melalui syslog
        // host atau socket TLS langsung ke collector SIEM yang dikonfigurasi.
        'audit' => [
            'driver' => 'stack',
            'channels' => $auditChannels,
            'ignore_exceptions' => true,
        ],
        'audit_file' => [
            'driver' => 'daily',
            'path' => storage_path('logs/audit.log'),
            'level' => 'info',
            'days' => env('AUDIT_LOG_DAYS', 365),
            'replace_placeholders' => true,
            'permission' => 0600,
        ],
        'audit_syslog' => [
            'driver' => 'syslog',
            'level' => 'info',
            'facility' => (int) env('AUDIT_SYSLOG_FACILITY', LOG_USER),
            'replace_placeholders' => true,
        ],
        'audit_remote_socket' => [
            'driver' => 'monolog',
            'level' => 'info',
            'handler' => SocketHandler::class,
            'handler_with' => [
                'connectionString' => $auditRemoteUrl,
                'timeout' => 2.0,
                'writingTimeout' => 2.0,
                'persistent' => false,
            ],
            'formatter' => JsonFormatter::class,
            'formatter_with' => [
                'batchMode' => JsonFormatter::BATCH_MODE_NEWLINES,
                'appendNewline' => true,
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'monthly' => [
            'driver' => 'monthly',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'max_files' => 3,
            'replace_placeholders' => true,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => env('LOG_SLACK_USERNAME', env('APP_NAME', 'Laravel')),
            'emoji' => env('LOG_SLACK_EMOJI', ':boom:'),
            'level' => env('LOG_LEVEL', 'critical'),
            'replace_placeholders' => true,
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => env('LOG_PAPERTRAIL_HANDLER', SyslogUdpHandler::class),
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
                'connectionString' => 'tls://'.env('PAPERTRAIL_URL').':'.env('PAPERTRAIL_PORT'),
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'handler_with' => [
                'stream' => 'php://stderr',
            ],
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
            'facility' => env('LOG_SYSLOG_FACILITY', LOG_USER),
            'replace_placeholders' => true,
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

    ],

];
