<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

// Lê o arquivo .env
$envFile = __DIR__ . '/../.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
        if ($l[0] !== '#' && str_contains($l, '=')) {
            [$k, $v] = explode('=', $l, 2);
            putenv(trim($k) . '=' . trim($v));
        }
    }
}
function env(string $k, string $d = ''): string { return getenv($k) ?: $d; }

// Conexão com o MySQL (PDO)
function db(): PDO {
    static $pdo;
    return $pdo ??= new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            env('DB_HOST', '127.0.0.1'), env('DB_PORT', '3306'), env('DB_NAME', 'gestor_estoque')),
        env('DB_USER', 'root'), env('DB_PASS', ''),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
}

// Responde em JSON e encerra
function out($data, int $code = 200): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function fail(string $msg, int $code = 422): never { out(['erro' => $msg], $code); }

// Lê o JSON enviado pelo frontend
function body(): array { return json_decode(file_get_contents('php://input'), true) ?? []; }
function cnpjValido(string $c): bool {
    if (strlen($c) !== 14 || preg_match('/^(\d)\1{13}$/', $c)) return false;
    foreach ([12, 13] as $n) {
        $s = 0; $p = $n - 7;
        for ($i = 0; $i < $n; $i++) { $s += (int)$c[$i] * $p--; if ($p < 2) $p = 9; }
        $d = $s % 11 < 2 ? 0 : 11 - $s % 11;
        if ((int)$c[$n] !== $d) return false;
    }
    return true;
}

function produtoInput(): array {
    $b = body();
    $d = [
        'empresa_id' => (int)($b['empresa_id'] ?? 0),
        'nome'       => trim((string)($b['nome'] ?? '')),
        'sku'        => trim((string)($b['sku'] ?? '')),
        'preco'      => $b['preco'] ?? null,
        'quantidade' => $b['quantidade'] ?? null,
    ];
    if ($d['nome'] === '' || $d['sku'] === '') fail('Nome e SKU são obrigatórios.');
    if (!is_numeric($d['preco']) || $d['preco'] < 0) fail('Preço inválido.');
    if (filter_var($d['quantidade'], FILTER_VALIDATE_INT) === false || $d['quantidade'] < 0) fail('Quantidade inválida.');
    $st = db()->prepare('SELECT 1 FROM empresas WHERE id = ?');
    $st->execute([$d['empresa_id']]);
    if (!$st->fetch()) fail('Empresa não encontrada.');
    return $d;
}
$parts  = explode('/', trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/')); // api/recurso/id
$method = $_SERVER['REQUEST_METHOD'];
$res    = $parts[1] ?? '';
$id     = isset($parts[2]) && ctype_digit($parts[2]) ? (int)$parts[2] : null;

try {
    $pdo = db();

    // ----- EMPRESAS -----
    if ($res === 'empresas') {
        if ($method === 'GET') {
            out($pdo->query(
                'SELECT e.*, COUNT(p.id) AS total_produtos
                 FROM empresas e LEFT JOIN produtos p ON p.empresa_id = e.id
                 GROUP BY e.id ORDER BY e.razao_social'
            )->fetchAll());
        }
        if ($method === 'POST') {
            $b = body();
            $d = [
                'razao_social'  => trim((string)($b['razao_social'] ?? '')),
                'cnpj'          => preg_replace('/\D/', '', (string)($b['cnpj'] ?? '')),
                'nome_fantasia' => trim((string)($b['nome_fantasia'] ?? '')),
                'endereco'      => trim((string)($b['endereco'] ?? '')),
                'uf'            => strtoupper(trim((string)($b['uf'] ?? ''))),
                'municipio'     => trim((string)($b['municipio'] ?? '')),
            ];
            foreach ($d as $campo => $v) if ($v === '') fail("Campo obrigatório: $campo.");
            if (!cnpjValido($d['cnpj'])) fail('CNPJ inválido.');
            $pdo->prepare('INSERT INTO empresas (razao_social,cnpj,nome_fantasia,endereco,uf,municipio)
                           VALUES (:razao_social,:cnpj,:nome_fantasia,:endereco,:uf,:municipio)')->execute($d);
            out(['id' => (int)$pdo->lastInsertId()] + $d, 201);
        }
    }

    // ----- PRODUTOS -----
    if ($res === 'produtos') {
        if ($method === 'GET' && $id) {
            $st = $pdo->prepare('SELECT * FROM produtos WHERE id = ?');
            $st->execute([$id]);
            out($st->fetch() ?: fail('Produto não encontrado.', 404));
        }
        if ($method === 'GET') {
            $sql = 'SELECT p.*, e.nome_fantasia AS empresa FROM produtos p
                    JOIN empresas e ON e.id = p.empresa_id';
            $args = [];
            if (!empty($_GET['empresa_id'])) { $sql .= ' WHERE p.empresa_id = ?'; $args[] = (int)$_GET['empresa_id']; }
            $st = $pdo->prepare($sql . ' ORDER BY p.nome');
            $st->execute($args);
            out($st->fetchAll());
        }
        if ($method === 'POST') {
            $d = produtoInput();
            $pdo->prepare('INSERT INTO produtos (empresa_id,nome,sku,preco,quantidade)
                           VALUES (:empresa_id,:nome,:sku,:preco,:quantidade)')->execute($d);
            out(['id' => (int)$pdo->lastInsertId()] + $d, 201);
        }
        if ($method === 'PUT' && $id) {
            $d = produtoInput() + ['id' => $id];
            $pdo->prepare('UPDATE produtos SET empresa_id=:empresa_id,nome=:nome,sku=:sku,
                           preco=:preco,quantidade=:quantidade WHERE id=:id')->execute($d);
            out($d);
        }
        if ($method === 'DELETE' && $id) {
            $st = $pdo->prepare('DELETE FROM produtos WHERE id = ?');
            $st->execute([$id]);
            $st->rowCount() ? out(['ok' => true]) : fail('Produto não encontrado.', 404);
        }
    }
        // ----- DASHBOARD -----
    if ($res === 'dashboard' && ($parts[2] ?? '') === 'stats' && $method === 'GET') {
        $tot = $pdo->query('SELECT
            (SELECT COUNT(*) FROM empresas) AS total_empresas,
            COUNT(*) AS total_produtos,
            COALESCE(SUM(preco * quantidade), 0) AS valor_estoque FROM produtos')->fetch();
        $empresas = $pdo->query('SELECT id, nome_fantasia, uf, municipio FROM empresas ORDER BY nome_fantasia')->fetchAll();
        $itens = $pdo->query('SELECT empresa_id, nome, sku, preco, quantidade FROM produtos ORDER BY nome')->fetchAll();
        foreach ($empresas as &$e) {
            $e['produtos'] = array_values(array_filter($itens, fn($i) => (int)$i['empresa_id'] === (int)$e['id']));
            $e['valor_estoque'] = array_sum(array_map(fn($i) => $i['preco'] * $i['quantidade'], $e['produtos']));
        }
        out(['total_empresas' => (int)$tot['total_empresas'], 'total_produtos' => (int)$tot['total_produtos'],
             'valor_estoque' => (float)$tot['valor_estoque'], 'empresas' => $empresas]);
    }

    fail('Rota não encontrada.', 404);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') fail('Registro duplicado (CNPJ ou SKU já cadastrado).', 409);
    fail('Erro no banco de dados.', 500);
}