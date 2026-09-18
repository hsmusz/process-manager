<?php

declare(strict_types=1);

namespace Tests\Unit;

use Movecloser\ProcessManager\Support\ErrorMessage;
use Tests\TestCase;

class ErrorMessageTest extends TestCase
{
    public function test_collapses_carriage_returns_and_new_lines(): void
    {
        $this->assertSame(
            'first second third',
            ErrorMessage::plain("first\r\nsecond\r\n\tthird")
        );
    }

    public function test_decodes_html_entities(): void
    {
        $this->assertSame('a & b > c', ErrorMessage::plain('a &amp; b &gt; c'));
    }

    public function test_keeps_json_payload_readable(): void
    {
        $json = '{"message":"Request does not match any route."}';

        $this->assertSame($json, ErrorMessage::plain($json));
    }

    public function test_limits_length(): void
    {
        $plain = ErrorMessage::plain(str_repeat('a', 500));

        $this->assertSame(ErrorMessage::MAX_LENGTH + 3, mb_strlen($plain));
    }

    public function test_replaces_invalid_utf8(): void
    {
        $plain = ErrorMessage::plain("bad \xC3\x28 byte");

        $this->assertTrue(mb_check_encoding($plain, 'UTF-8'));
        $this->assertStringContainsString('byte', $plain);
    }

    public function test_strips_base_tag_from_error_page(): void
    {
        $page = '<!doctype html> <html> <head> <title>Error 503: Service Unavailable</title> '
            . '<base href="https&#x3A;&#x2F;&#x2F;mage.example.dev&#x2F;errors&#x2F;default&#x2F;" /> '
            . '</head> <body> <h1>Service Temporarily Unavailable</h1> </body> </html>';

        $plain = ErrorMessage::plain($page);

        $this->assertStringNotContainsString('<', $plain);
        $this->assertStringNotContainsString('base href', $plain);
        $this->assertSame('Error 503: Service Unavailable Service Temporarily Unavailable', $plain);
    }

    public function test_strips_nginx_error_page_to_single_line(): void
    {
        $page = "<html>\r\n<head><title>502 Bad Gateway</title></head>\r\n<body>\r\n"
            . "<center><h1>502 Bad Gateway</h1></center>\r\n<hr><center>nginx</center>\r\n</body>\r\n</html>\r\n";

        $this->assertSame('502 Bad Gateway 502 Bad Gateway nginx', ErrorMessage::plain($page));
    }

    public function test_strips_script_and_style_contents(): void
    {
        $page = '<style>body { color: red }</style><script>alert("x")</script><p>message</p>';

        $this->assertSame('message', ErrorMessage::plain($page));
    }
}
