<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/db.php';

$categoriasIngreso = ['Salario', 'Venta', 'Regalo', 'Freelance', 'Inversión', 'Otros'];
$categoriasGasto = [
    'Comida', 'Transporte', 'Servicios', 'Entretenimiento',
    'Salud', 'Educación', 'Compras', 'Vivienda', 'Suscripciones', 'Otros'
];

function e(string $valor): string {
    return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
}

function dinero(float $monto): string {
    return '$' . number_format($monto, 2);
}

function fecha_valida(string $fecha): bool {
    $d = DateTime::createFromFormat('Y-m-d', $fecha);
    return $d && $d->format('Y-m-d') === $fecha;
}

function limpiar(string $valor): string {
    return trim($valor);
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function validar_csrf(): void {
    $token = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(403);
        exit('Solicitud no válida.');
    }
}

function flash(string $mensaje, string $tipo = 'ok'): never {
    $_SESSION['flash'] = ['mensaje' => $mensaje, 'tipo' => $tipo];
    header('Location: index.php');
    exit;
}

function categoria_permitida(string $tipo, string $categoria, array $ingresos, array $gastos): bool {
    $permitidas = $tipo === 'Ingreso' ? $ingresos : $gastos;
    return in_array($categoria, $permitidas, true);
}

try {
    $pdo = db();
} catch (Throwable $e) {
    http_response_code(500);
    ?>
    <!doctype html>
    <html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>MiControl - Error de conexión</title>
        <style>
            body{margin:0;background:#07111f;color:#dbeafe;font-family:Segoe UI,Arial,sans-serif;padding:40px}
            .box{max-width:760px;margin:auto;background:#0d1b2a;border:1px solid #22334b;border-radius:18px;padding:28px}
            code{background:#132238;padding:3px 7px;border-radius:7px}
            h1{color:#7dd3fc}
        </style>
    </head>
    <body>
    <div class="box">
        <h1>No se pudo conectar a MySQL</h1>
        <p>Importa primero <code>micontrol_futurista.sql</code> en phpMyAdmin y confirma que MySQL esté iniciado.</p>
        <p>También revisa <code>config.php</code>.</p>
        <p><strong>Detalle:</strong> <?= e($e->getMessage()) ?></p>
    </div>
    </body>
    </html>
    <?php
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar_movimiento' || $accion === 'actualizar_movimiento') {
        $tipo = limpiar($_POST['tipo'] ?? '');
        $categoria = limpiar($_POST['categoria'] ?? '');
        $descripcion = limpiar($_POST['descripcion'] ?? '');
        $nota = limpiar($_POST['nota'] ?? '');
        $monto = (float)str_replace(',', '', $_POST['monto'] ?? '0');
        $fecha = limpiar($_POST['fecha'] ?? '');

        if (!in_array($tipo, ['Ingreso', 'Gasto'], true)) {
            flash('Selecciona un tipo válido.', 'error');
        }
        if (!categoria_permitida($tipo, $categoria, $categoriasIngreso, $categoriasGasto)) {
            flash('Selecciona una categoría válida.', 'error');
        }
        if ($descripcion === '') {
            flash('La descripción es obligatoria.', 'error');
        }
        if ($monto <= 0) {
            flash('El monto debe ser mayor que cero.', 'error');
        }
        if (!fecha_valida($fecha)) {
            flash('Selecciona una fecha válida.', 'error');
        }

        if ($accion === 'guardar_movimiento') {
            $stmt = $pdo->prepare(
                'INSERT INTO movimientos (tipo,categoria,descripcion,nota,monto,fecha)
                 VALUES (?,?,?,?,?,?)'
            );
            $stmt->execute([$tipo, $categoria, $descripcion, $nota, $monto, $fecha]);
            flash('Movimiento registrado correctamente.');
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            flash('Movimiento no válido.', 'error');
        }

        $stmt = $pdo->prepare(
            'UPDATE movimientos
             SET tipo=?,categoria=?,descripcion=?,nota=?,monto=?,fecha=?
             WHERE id=?'
        );
        $stmt->execute([$tipo, $categoria, $descripcion, $nota, $monto, $fecha, $id]);
        flash('Movimiento actualizado correctamente.');
    }

    if ($accion === 'eliminar_movimiento') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            flash('Movimiento no válido.', 'error');
        }
        $stmt = $pdo->prepare('DELETE FROM movimientos WHERE id=?');
        $stmt->execute([$id]);
        flash('Movimiento eliminado.');
    }

    if ($accion === 'guardar_presupuesto') {
        $limite = (float)str_replace(',', '', $_POST['limite'] ?? '0');
        $anio = (int)($_POST['anio'] ?? date('Y'));
        $mes = (int)($_POST['mes'] ?? date('n'));

        if ($limite < 0 || $anio < 2000 || $anio > 2100 || $mes < 1 || $mes > 12) {
            flash('Datos de presupuesto no válidos.', 'error');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO presupuesto_mensual (anio,mes,limite)
             VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE limite=VALUES(limite)'
        );
        $stmt->execute([$anio, $mes, $limite]);
        flash('Presupuesto mensual actualizado.');
    }
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$buscar = limpiar($_GET['buscar'] ?? '');
$filtroTipo = limpiar($_GET['tipo'] ?? 'Todos');
$desde = limpiar($_GET['desde'] ?? '');
$hasta = limpiar($_GET['hasta'] ?? '');

$where = [];
$params = [];

if (in_array($filtroTipo, ['Ingreso', 'Gasto'], true)) {
    $where[] = 'tipo=?';
    $params[] = $filtroTipo;
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

$sql = 'SELECT * FROM movimientos';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY fecha DESC,id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$movimientos = $stmt->fetchAll();

$anioActual = (int)date('Y');
$mesActual = (int)date('n');
$inicioMes = date('Y-m-01');
$finMes = date('Y-m-t');

$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN tipo='Ingreso' THEN monto ELSE 0 END),0) ingresos,
        COALESCE(SUM(CASE WHEN tipo='Gasto' THEN monto ELSE 0 END),0) gastos
    FROM movimientos
    WHERE fecha BETWEEN ? AND ?
");
$stmt->execute([$inicioMes, $finMes]);
$resumenMes = $stmt->fetch();
$ingresosMes = (float)$resumenMes['ingresos'];
$gastosMes = (float)$resumenMes['gastos'];
$saldoMes = $ingresosMes - $gastosMes;
$ahorroPct = $ingresosMes > 0 ? ($saldoMes / $ingresosMes) * 100 : 0;

$stmt = $pdo->prepare('SELECT limite FROM presupuesto_mensual WHERE anio=? AND mes=?');
$stmt->execute([$anioActual, $mesActual]);
$presupuesto = (float)($stmt->fetchColumn() ?: 0);
$usoPresupuesto = $presupuesto > 0 ? min(100, ($gastosMes / $presupuesto) * 100) : 0;

$stmt = $pdo->prepare("
    SELECT categoria, SUM(monto) total
    FROM movimientos
    WHERE tipo='Gasto' AND fecha BETWEEN ? AND ?
    GROUP BY categoria
    ORDER BY total DESC
");
$stmt->execute([$inicioMes, $finMes]);
$gastosCategoria = $stmt->fetchAll();

$labelsCategoria = array_column($gastosCategoria, 'categoria');
$dataCategoria = array_map('floatval', array_column($gastosCategoria, 'total'));

$meses = [];
$datosIngresos = [];
$datosGastos = [];

for ($i = 5; $i >= 0; $i--) {
    $inicio = date('Y-m-01', strtotime("-$i months"));
    $fin = date('Y-m-t', strtotime("-$i months"));
    $etiqueta = date('M Y', strtotime($inicio));

    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN tipo='Ingreso' THEN monto ELSE 0 END),0) ingresos,
            COALESCE(SUM(CASE WHEN tipo='Gasto' THEN monto ELSE 0 END),0) gastos
        FROM movimientos
        WHERE fecha BETWEEN ? AND ?
    ");
    $stmt->execute([$inicio, $fin]);
    $fila = $stmt->fetch();

    $meses[] = $etiqueta;
    $datosIngresos[] = (float)$fila['ingresos'];
    $datosGastos[] = (float)$fila['gastos'];
}

$stmt = $pdo->prepare("
    SELECT * FROM movimientos
    WHERE fecha BETWEEN ? AND ?
    ORDER BY fecha DESC,id DESC
    LIMIT 6
");
$stmt->execute([$inicioMes, $finMes]);
$recientes = $stmt->fetchAll();

$editar = null;
if (isset($_GET['editar'])) {
    $idEditar = (int)$_GET['editar'];
    if ($idEditar > 0) {
        $stmt = $pdo->prepare('SELECT * FROM movimientos WHERE id=?');
        $stmt->execute([$idEditar]);
        $editar = $stmt->fetch() ?: null;
    }
}

$formTipo = $editar['tipo'] ?? 'Gasto';
$formCategorias = $formTipo === 'Ingreso' ? $categoriasIngreso : $categoriasGasto;
$formCategoria = $editar['categoria'] ?? $formCategorias[0];
$formDescripcion = $editar['descripcion'] ?? '';
$formNota = $editar['nota'] ?? '';
$formMonto = isset($editar['monto']) ? (string)$editar['monto'] : '';
$formFecha = $editar['fecha'] ?? date('Y-m-d');

$mesesNombre = [
    1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',
    7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'
];

$exportQuery = http_build_query([
    'buscar'=>$buscar,
    'tipo'=>$filtroTipo,
    'desde'=>$desde,
    'hasta'=>$hasta
]);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MiControl Nova</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-mark">M</div>
            <div>
                <strong>MiControl</strong>
                <span>NOVA</span>
            </div>
        </div>

        <nav class="nav">
            <a href="#dashboard" class="nav-link active"><span>◈</span> Dashboard</a>
            <a href="#movimientos" class="nav-link"><span>↕</span> Movimientos</a>
            <a href="#analitica" class="nav-link"><span>⌁</span> Analítica</a>
            <a href="#presupuesto" class="nav-link"><span>◎</span> Presupuesto</a>
        </nav>

        <div class="side-card">
            <span>Estado del sistema</span>
            <strong><i></i> MySQL conectado</strong>
            <small><?= e(DB_NAME) ?></small>
        </div>

        <div class="side-footer">
            <span>PHP + MySQL</span>
            <small>Gestión financiera local</small>
        </div>
    </aside>

    <div class="main-area">
        <header class="topbar">
            <div>
                <span class="eyebrow">CENTRO FINANCIERO PERSONAL</span>
                <h1>Panel de control</h1>
            </div>
            <div class="top-actions">
                <span class="date-chip"><?= e($mesesNombre[$mesActual] . ' ' . $anioActual) ?></span>
                <a class="ghost-btn" href="exportar.php?<?= e($exportQuery) ?>">Exportar CSV</a>
                <button class="primary-btn" type="button" data-open-modal>+ Nuevo movimiento</button>
            </div>
        </header>

        <main class="content-wrap">
            <?php if ($flash): ?>
                <div class="alert <?= $flash['tipo']==='error' ? 'alert-error' : 'alert-ok' ?>">
                    <?= e($flash['mensaje']) ?>
                </div>
            <?php endif; ?>

            <section id="dashboard" class="hero-grid">
                <article class="metric metric-accent">
                    <div class="metric-head">
                        <span>Ingresos del mes</span>
                        <span class="metric-icon">↗</span>
                    </div>
                    <strong><?= dinero($ingresosMes) ?></strong>
                    <small>Entradas registradas en <?= e($mesesNombre[$mesActual]) ?></small>
                </article>

                <article class="metric">
                    <div class="metric-head">
                        <span>Gastos del mes</span>
                        <span class="metric-icon">↘</span>
                    </div>
                    <strong><?= dinero($gastosMes) ?></strong>
                    <small>Salidas registradas durante el mes</small>
                </article>

                <article class="metric">
                    <div class="metric-head">
                        <span>Saldo actual</span>
                        <span class="metric-icon">◉</span>
                    </div>
                    <strong class="<?= $saldoMes < 0 ? 'negative' : '' ?>"><?= dinero($saldoMes) ?></strong>
                    <small>Ingresos menos gastos del mes</small>
                </article>

                <article class="metric">
                    <div class="metric-head">
                        <span>Tasa de ahorro</span>
                        <span class="metric-icon">⌁</span>
                    </div>
                    <strong><?= number_format($ahorroPct, 1) ?>%</strong>
                    <small>Porcentaje del ingreso no gastado</small>
                </article>
            </section>

            <section class="dashboard-grid">
                <article class="panel trend-panel">
                    <div class="panel-head">
                        <div>
                            <span class="panel-kicker">TENDENCIA</span>
                            <h2>Flujo de efectivo</h2>
                        </div>
                        <span class="mini-chip">Últimos 6 meses</span>
                    </div>
                    <canvas id="trendChart" height="290" aria-label="Gráfica de ingresos y gastos"></canvas>
                    <div class="legend">
                        <span><i class="legend-income"></i> Ingresos</span>
                        <span><i class="legend-expense"></i> Gastos</span>
                    </div>
                </article>

                <article class="panel budget-panel" id="presupuesto">
                    <div class="panel-head">
                        <div>
                            <span class="panel-kicker">CONTROL</span>
                            <h2>Presupuesto mensual</h2>
                        </div>
                        <span class="mini-chip"><?= e($mesesNombre[$mesActual]) ?></span>
                    </div>

                    <div class="budget-ring-wrap">
                        <div class="budget-ring" style="--progress: <?= number_format($usoPresupuesto, 2, '.', '') ?>deg">
                            <div>
                                <strong><?= number_format($usoPresupuesto, 0) ?>%</strong>
                                <span>utilizado</span>
                            </div>
                        </div>
                    </div>

                    <div class="budget-numbers">
                        <div><span>Gastado</span><strong><?= dinero($gastosMes) ?></strong></div>
                        <div><span>Límite</span><strong><?= dinero($presupuesto) ?></strong></div>
                    </div>

                    <form method="post" class="budget-form">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="accion" value="guardar_presupuesto">
                        <input type="hidden" name="anio" value="<?= $anioActual ?>">
                        <input type="hidden" name="mes" value="<?= $mesActual ?>">
                        <label for="limite">Definir límite mensual</label>
                        <div class="budget-input">
                            <span>$</span>
                            <input id="limite" name="limite" type="number" min="0" step="0.01"
                                   value="<?= e((string)$presupuesto) ?>" required>
                            <button type="submit">Guardar</button>
                        </div>
                    </form>
                </article>
            </section>

            <section id="movimientos" class="panel movements-panel">
                <div class="panel-head panel-head-wrap">
                    <div>
                        <span class="panel-kicker">REGISTROS</span>
                        <h2>Movimientos financieros</h2>
                    </div>
                    <div class="head-actions">
                        <span class="mini-chip"><?= count($movimientos) ?> resultados</span>
                        <button class="primary-btn small" type="button" data-open-modal>+ Agregar</button>
                    </div>
                </div>

                <form method="get" class="filters">
                    <div class="search-field">
                        <span>⌕</span>
                        <input type="search" name="buscar" placeholder="Buscar movimiento..."
                               value="<?= e($buscar) ?>">
                    </div>
                    <select name="tipo">
                        <option value="Todos" <?= $filtroTipo==='Todos'?'selected':'' ?>>Todos los tipos</option>
                        <option value="Ingreso" <?= $filtroTipo==='Ingreso'?'selected':'' ?>>Ingresos</option>
                        <option value="Gasto" <?= $filtroTipo==='Gasto'?'selected':'' ?>>Gastos</option>
                    </select>
                    <input type="date" name="desde" value="<?= e($desde) ?>">
                    <input type="date" name="hasta" value="<?= e($hasta) ?>">
                    <button class="ghost-btn" type="submit">Aplicar filtros</button>
                    <a class="text-link" href="index.php#movimientos">Limpiar</a>
                </form>

                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Categoría</th>
                            <th>Descripción</th>
                            <th>Nota</th>
                            <th class="right">Monto</th>
                            <th>Acciones</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$movimientos): ?>
                            <tr><td colspan="7" class="empty">No hay movimientos para mostrar.</td></tr>
                        <?php else: ?>
                            <?php foreach ($movimientos as $m): ?>
                                <tr>
                                    <td><?= e($m['fecha']) ?></td>
                                    <td><span class="type-pill <?= $m['tipo']==='Ingreso'?'income':'expense' ?>"><?= e($m['tipo']) ?></span></td>
                                    <td><?= e($m['categoria']) ?></td>
                                    <td class="main-cell"><?= e($m['descripcion']) ?></td>
                                    <td class="muted-cell"><?= e($m['nota'] ?: '—') ?></td>
                                    <td class="right amount <?= $m['tipo']==='Ingreso'?'income-text':'expense-text' ?>">
                                        <?= $m['tipo']==='Ingreso' ? '+' : '-' ?><?= dinero((float)$m['monto']) ?>
                                    </td>
                                    <td class="actions">
                                        <a href="?editar=<?= (int)$m['id'] ?>#movimientos" class="icon-link" title="Editar">✎</a>
                                        <form method="post" class="inline delete-form">
                                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="accion" value="eliminar_movimiento">
                                            <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                                            <button class="icon-link danger" type="submit" title="Eliminar">⌫</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section id="analitica" class="analytics-grid">
                <article class="panel">
                    <div class="panel-head">
                        <div>
                            <span class="panel-kicker">ANÁLISIS</span>
                            <h2>Gasto por categoría</h2>
                        </div>
                        <span class="mini-chip">Mes actual</span>
                    </div>
                    <?php if (!$gastosCategoria): ?>
                        <div class="empty-chart">Aún no hay gastos este mes.</div>
                    <?php else: ?>
                        <canvas id="categoryChart" height="300" aria-label="Gráfica de gastos por categoría"></canvas>
                    <?php endif; ?>
                </article>

                <article class="panel">
                    <div class="panel-head">
                        <div>
                            <span class="panel-kicker">ACTIVIDAD</span>
                            <h2>Movimientos recientes</h2>
                        </div>
                        <span class="mini-chip">Mes actual</span>
                    </div>

                    <div class="recent-list">
                        <?php if (!$recientes): ?>
                            <div class="empty-chart">No hay actividad reciente.</div>
                        <?php else: ?>
                            <?php foreach ($recientes as $r): ?>
                                <div class="recent-item">
                                    <div class="recent-icon <?= $r['tipo']==='Ingreso'?'income':'expense' ?>">
                                        <?= $r['tipo']==='Ingreso' ? '↗' : '↘' ?>
                                    </div>
                                    <div class="recent-main">
                                        <strong><?= e($r['descripcion']) ?></strong>
                                        <span><?= e($r['categoria']) ?> · <?= e($r['fecha']) ?></span>
                                    </div>
                                    <strong class="<?= $r['tipo']==='Ingreso'?'income-text':'expense-text' ?>">
                                        <?= $r['tipo']==='Ingreso' ? '+' : '-' ?><?= dinero((float)$r['monto']) ?>
                                    </strong>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </article>
            </section>
        </main>
    </div>
</div>

<div class="modal <?= $editar ? 'show' : '' ?>" id="movementModal" aria-hidden="<?= $editar ? 'false' : 'true' ?>">
    <div class="modal-backdrop" data-close-modal></div>
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="modal-head">
            <div>
                <span class="panel-kicker">REGISTRO FINANCIERO</span>
                <h2 id="modalTitle"><?= $editar ? 'Editar movimiento' : 'Nuevo movimiento' ?></h2>
            </div>
            <button type="button" class="close-btn" data-close-modal>×</button>
        </div>

        <form method="post" class="movement-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="accion" value="<?= $editar ? 'actualizar_movimiento' : 'guardar_movimiento' ?>">
            <?php if ($editar): ?>
                <input type="hidden" name="id" value="<?= (int)$editar['id'] ?>">
            <?php endif; ?>

            <div class="form-grid">
                <div>
                    <label for="tipoMovimiento">Tipo</label>
                    <select id="tipoMovimiento" name="tipo" required>
                        <option value="Ingreso" <?= $formTipo==='Ingreso'?'selected':'' ?>>Ingreso</option>
                        <option value="Gasto" <?= $formTipo==='Gasto'?'selected':'' ?>>Gasto</option>
                    </select>
                </div>
                <div>
                    <label for="categoriaMovimiento">Categoría</label>
                    <select id="categoriaMovimiento" name="categoria"
                            data-selected="<?= e($formCategoria) ?>" required></select>
                </div>
                <div class="full">
                    <label for="descripcion">Descripción</label>
                    <input id="descripcion" name="descripcion" type="text" maxlength="255"
                           value="<?= e($formDescripcion) ?>" placeholder="Ej. Supermercado de la semana" required>
                </div>
                <div>
                    <label for="monto">Monto</label>
                    <input id="monto" name="monto" type="number" min="0.01" step="0.01"
                           value="<?= e($formMonto) ?>" placeholder="0.00" required>
                </div>
                <div>
                    <label for="fecha">Fecha</label>
                    <input id="fecha" name="fecha" type="date" value="<?= e($formFecha) ?>" required>
                </div>
                <div class="full">
                    <label for="nota">Nota opcional</label>
                    <textarea id="nota" name="nota" rows="3" maxlength="300"
                              placeholder="Información adicional..."><?= e($formNota) ?></textarea>
                </div>
            </div>

            <div class="modal-actions">
                <?php if ($editar): ?>
                    <a href="index.php#movimientos" class="ghost-btn">Cancelar</a>
                <?php else: ?>
                    <button type="button" class="ghost-btn" data-close-modal>Cancelar</button>
                <?php endif; ?>
                <button type="submit" class="primary-btn"><?= $editar ? 'Guardar cambios' : 'Registrar movimiento' ?></button>
            </div>
        </form>
    </div>
</div>

<script>
window.MICONTROL_DATA = <?= json_encode([
    'categorias'=>[
        'Ingreso'=>$categoriasIngreso,
        'Gasto'=>$categoriasGasto
    ],
    'trend'=>[
        'labels'=>$meses,
        'ingresos'=>$datosIngresos,
        'gastos'=>$datosGastos
    ],
    'category'=>[
        'labels'=>$labelsCategoria,
        'values'=>$dataCategoria
    ]
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="assets/app.js"></script>
</body>
</html>
