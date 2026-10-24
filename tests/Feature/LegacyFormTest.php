<?php
namespace Tests\Feature;

use App\Support\LegacyForm;
use Tests\TestCase;

class LegacyFormTest extends TestCase
{
    public function test_retained_forms_escape_values_and_include_csrf_and_method_fields(): void
    {
        $form = new LegacyForm();
        $html = $form->model((object) ['name' => '<script>alert(1)</script>'], ['url' => '/example', 'method' => 'PATCH']);
        $this->assertStringContainsString('name="_token"', $html);
        $this->assertStringContainsString('name="_method" value="PATCH"', $html);
        $input = $form->text('name');
        $this->assertStringContainsString('&lt;script&gt;', $input);
        $this->assertStringNotContainsString('<script>', $input);
        $this->assertStringNotContainsString('value=', $form->password('password'));
        $form->close();
        $this->assertStringNotContainsString('&lt;script&gt;', $form->text('name'));
    }
}
