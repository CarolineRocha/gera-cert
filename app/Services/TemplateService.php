<?php

namespace App\Services;

class TemplateService
{
    public function render($html, $data = [])
    {
        foreach ($data as $key => $value) {

            $html = str_replace(
                '{{' . $key . '}}',
                htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                $html
            );
        }

        return $html;
    }
}
