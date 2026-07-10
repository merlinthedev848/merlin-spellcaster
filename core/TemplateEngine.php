<?php
/**
 * core/TemplateEngine.php — Simple variable substitution + basic conditionals
 * PHP 8.5+ — zero dependencies
 */
declare(strict_types=1);

class TemplateEngine
{
    /**
     * Render a template string with the given variables.
     * Supports: {{variable}}, {{if variable}}…{{/if}}, {{if !variable}}…{{/if}}
     */
    public function render(string $template, array $vars): string
    {
        // Process conditionals: {{if variable}} ... {{/if}}
        $template = preg_replace_callback(
            '/\{\{if\s+(!?)(\w+)\}\}(.*?)\{\{\/if\}\}/si',
            function (array $m) use ($vars): string {
                $negate = $m[1] === '!';
                $key    = $m[2];
                $inner  = $m[3];
                $value  = $vars[$key] ?? '';
                $show   = $negate ? empty($value) : !empty($value);
                return $show ? $inner : '';
            },
            $template
        ) ?? $template;

        // Process loops: {{each items as item}} ... {{/each}}
        $template = preg_replace_callback(
            '/\{\{each\s+(\w+)\s+as\s+(\w+)\}\}(.*?)\{\{\/each\}\}/si',
            function (array $m) use ($vars): string {
                $listKey  = $m[1];
                $itemKey  = $m[2];
                $inner    = $m[3];
                $items    = $vars[$listKey] ?? [];
                if (!is_array($items)) return '';
                $out = '';
                foreach ($items as $item) {
                    $itemVars = is_array($item) ? $item : [$itemKey => $item];
                    $out .= $this->render($inner, $itemVars);
                }
                return $out;
            },
            $template
        ) ?? $template;

        // Process variables: {{variable}} and {{variable|filter}}
        $template = preg_replace_callback(
            '/\{\{(\w+)(?:\|(\w+))?\}\}/',
            function (array $m) use ($vars): string {
                $key    = $m[1];
                $filter = $m[2] ?? '';
                $value  = (string)($vars[$key] ?? '');
                return match ($filter) {
                    'upper'   => strtoupper($value),
                    'lower'   => strtolower($value),
                    'ucfirst' => ucfirst($value),
                    'escape', 'e' => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                    'nl2br'   => nl2br(htmlspecialchars($value, ENT_QUOTES, 'UTF-8')),
                    default   => $value,
                };
            },
            $template
        ) ?? $template;

        return $template;
    }

    /**
     * Render a template from the templates table
     */
    public function renderFromDb(PDO $db, int $templateId, array $vars): ?string
    {
        $st = $db->prepare("SELECT body_html FROM templates WHERE id = ?");
        $st->execute([$templateId]);
        $tpl = $st->fetchColumn();
        if (!$tpl) return null;
        return $this->render($tpl, $vars);
    }
}
