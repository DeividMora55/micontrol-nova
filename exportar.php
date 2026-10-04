<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

function fecha_valida(string $fecha): bool {
    $d = DateTime::createFromFormat('Y-m-d', $fecha);
    return $d && $d->format('Y-m-d') === $fecha;
}

$pdo = db();
$buscar = trim($_GET['buscar'] ?? '');
$tipo = trim($_GET['tipo'] ?? 'Todos');
$desde = trim($_GET['desde'] ?? '');
$hasta = trim($_GET['hasta'] ?? '');

$where = [];
$params = [];

if (in_array($tipo, ['Ingreso', 'Gasto'], true)) {
    $where[] = 'tipo=?';
    $params[] = $tipo;
}

if ($buscar !== '') {
    $where[] = '(descripcion LIKE ? OR nota LIKE ? OR categoria LIKE ? OR CAST(monto AS CHAR) LIKE ?)';
    $q = '%' . $buscar . '%';
    array_push($params, $q, $q, $q, $q);
}

if ($desde !== '' && fecha_valida($desde)) {
    $where[] = 'fecha>=?';
    $params[] = $desde;
}

if ($hasta !== '' && fecha_valida($hasta)) {
    $where[] = 'fecha<=?';
    $params[] = $hasta;
}

$sql = 'SELECT fecha,tipo,categoria,descripcion,nota,monto FROM movimientos';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY fecha DESC,id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="movimientos_micontrol.csv"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['Fecha', 'Tipo', 'Categoría', 'Descripción', 'Nota', 'Monto']);

while ($fila = $stmt->fetch()) {
    fputcsv($out, [
        $fila['fecha'],
        $fila['tipo'],
        $fila['categoria'],
        $fila['descripcion'],
        $fila['nota'],
        number_format((float)$fila['monto'], 2, '.', '')
    ]);
}

fclose($out);
exit;
?>
