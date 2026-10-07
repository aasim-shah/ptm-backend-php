<?php
/**
 * Exports every registered route with its handler location and the request
 * fields the handler reads. Used by scripts/make-postman.py.
 *
 *   php scripts/export-routes.php > build/routes.json
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$skipPrefixes = ['_ignition', 'sanctum/', '_debugbar'];
$out = [];

foreach (app('router')->getRoutes() as $route) {
    $uri = $route->uri();
    foreach ($skipPrefixes as $p) {
        if (str_starts_with($uri, $p)) {
            continue 2;
        }
    }

    $action = $route->getActionName();
    $entry = [
        'methods' => array_values(array_diff($route->methods(), ['HEAD'])),
        'uri' => '/' . ltrim($uri, '/'),
        'name' => $route->getName(),
        'action' => $action,
        'middleware' => $route->gatherMiddleware(),
        'file' => null,
        'line' => null,
        'params' => [],
        'rules' => [],
    ];

    if (str_contains($action, '@')) {
        [$class, $method] = explode('@', $action);
        if (method_exists($class, $method)) {
            $ref = new ReflectionMethod($class, $method);
            $file = $ref->getFileName();
            $entry['file'] = str_replace(base_path() . '/', '', $file);
            $entry['line'] = $ref->getStartLine();
            $lines = file($file);
            $body = implode('', array_slice($lines, $ref->getStartLine() - 1, $ref->getEndLine() - $ref->getStartLine() + 1));
            $body = preg_replace('~/\*.*?\*/~s', '', $body);
            $body = preg_replace('~^\s*//.*$~m', '', $body);

            $params = [];
            preg_match_all('~\$request->(?:input|get|has|filled|file|hasFile|query|boolean)\(\s*[\'"](\w+)~', $body, $m);
            $params = array_merge($params, $m[1]);
            preg_match_all('~\$request->(\w+)\b(?!\s*\()~', $body, $m);
            $params = array_merge($params, $m[1]);
            preg_match_all('~\$request\[[\'"](\w+)~', $body, $m);
            $params = array_merge($params, $m[1]);
            preg_match_all('~[\'"]([\w.*]+)[\'"]\s*=>\s*[\'"]((?:required|nullable|sometimes|array|numeric|integer|string|email|date|file|image|in:)[^\'"]*)[\'"]~', $body, $m, PREG_SET_ORDER);
            foreach ($m as $rule) {
                $entry['rules'][$rule[1]] = $rule[2];
                $params[] = explode('.', $rule[1])[0];
            }
            $ignore = ['user', 'all', 'only', 'except', 'file', 'input', 'ip', 'header', 'headers', 'method', 'route',
                'session', 'merge', 'bearerToken', 'wantsJson', 'expectsJson', 'url', 'fullUrl', 'path', 'query',
                'files', 'request', 'server', 'cookies', 'attributes', 'json', 'isMethod', 'validate', 'hasFile', 'ajax'];
            $entry['params'] = array_values(array_unique(array_diff($params, $ignore)));
            sort($entry['params']);
        }
    }

    $out[] = $entry;
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
