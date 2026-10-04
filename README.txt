MiControl NOVA - PHP + MySQL
==============================

Versión rediseñada con una interfaz futurista y más completa.

FUNCIONES
---------
- Dashboard con indicadores del mes.
- Total de ingresos, gastos, saldo y tasa de ahorro.
- Registro de ingresos y gastos.
- Edición y eliminación de movimientos.
- Campo de nota opcional.
- Búsqueda.
- Filtro por tipo.
- Filtro por rango de fechas.
- Presupuesto mensual editable.
- Indicador visual de uso del presupuesto.
- Gráfica de ingresos y gastos de los últimos 6 meses.
- Gráfica de gastos por categoría.
- Lista de movimientos recientes.
- Exportación de movimientos a CSV.
- Protección CSRF para operaciones.
- Consultas preparadas con PDO.
- Diseño responsivo.

INSTALACIÓN EN XAMPP
--------------------
1. Descomprime el archivo MiControl_NOVA_htdocs.zip.
2. Copia la carpeta "MiControl_NOVA" en:

   C:\xampp\htdocs\

3. Inicia Apache y MySQL en XAMPP.

4. Abre phpMyAdmin:
   http://localhost/phpmyadmin/

5. Importa por separado el archivo:
   micontrol_futurista.sql

6. Abre:
   http://localhost/MiControl_NOVA/

CONFIGURACIÓN
-------------
La configuración por defecto está en config.php:

Host: localhost
Puerto: 3306
Base de datos: micontrol_gastos
Usuario: root
Contraseña: vacía

Si MySQL tiene contraseña, cambia DB_PASS en config.php.

ARCHIVOS
--------
index.php           Aplicación principal.
db.php              Conexión PDO.
config.php          Credenciales de MySQL.
exportar.php        Exportación CSV.
assets/styles.css   Diseño futurista.
assets/app.js       Modal y gráficas.
