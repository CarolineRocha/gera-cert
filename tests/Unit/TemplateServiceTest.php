<?php

namespace Tests\Unit;

use App\Services\TemplateService;
use PHPUnit\Framework\TestCase;

class TemplateServiceTest extends TestCase
{
    public function test_placeholder_values_are_replaced_and_html_escaped(): void
    {
        $service = new TemplateService();

        $this->assertSame(
            '<p>Ana &amp; Bia</p><span>&lt;script&gt;</span>',
            $service->render('<p>{{name}}</p><span>{{payload}}</span>', [
                'name' => 'Ana & Bia',
                'payload' => '<script>',
            ])
        );
    }

    public function test_missing_placeholder_is_left_unchanged(): void
    {
        $this->assertSame('<p>{{name}}</p>', (new TemplateService())->render('<p>{{name}}</p>'));
    }
}
