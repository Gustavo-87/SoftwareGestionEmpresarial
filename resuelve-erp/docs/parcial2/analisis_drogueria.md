
# Análisis de la Droguería Vida Sana

## 1. Flujo integrado

1. El administrador identifica un medicamento con existencias bajas.
2. El administrador crea una orden de compra al proveedor.
3. El auxiliar de bodega descarga y organiza la mercancía recibida.
4. El regente verifica los medicamentos, registra los lotes, cantidades y fechas de vencimiento y aprueba su recepción.
5. El sistema actualiza el inventario y el stock total.
6. El cajero registra la venta y el sistema selecciona primero los lotes con vencimiento más próximo mediante FEFO.
7. Para medicamentos de control especial se requiere autorización del regente.
8. El sistema genera el comprobante de venta y actualiza los indicadores.

## 2. Reglas de negocio

| Regla | Módulo | Implementación |
|---|---|---|
| No vender lotes vencidos | Ventas | DispensingService |
| Aplicar FEFO | Inventario | DispensingService |
| Impedir ventas sin stock suficiente | Ventas | Servicio y ValidationException |
| Separar creación y recepción de compras | Compras | Middleware, políticas y servicio |
| Exigir autorización del regente para control especial | Ventas | Política y servicio |
| Evitar lotes duplicados por producto | Inventario | Índice único en migración |
| Validar cantidades positivas | Inventario y ventas | Form Request y servicio |
| Registrar cada recepción con su lote | Compras | Servicio transaccional |

## 3. Estados

### Orden de compra

- Borrador: no modifica el inventario.
- Aprobada: habilita la recepción, sin modificar existencias.
- Parcialmente recibida: aumenta las existencias por las cantidades recibidas.
- Recibida: todas las cantidades fueron recibidas y registradas.
- Cancelada: no permite nuevas recepciones y conserva los movimientos ya realizados.

### Venta

- Pendiente: no descuenta inventario.
- Autorizada: permite continuar cuando se cumplen las condiciones de dispensación.
- Completada: descuenta los lotes y el stock total.
- Rechazada: no modifica las existencias.
- Anulada: requiere un movimiento de reversión controlado si ya fue completada.

## 4. Indicadores KPI

1. Unidades próximas a vencer en los siguientes 30 días.
2. Productos con existencias inferiores al mínimo establecido.
3. Ventas totales por día y por mes.
4. Cantidad de productos vencidos que deben retirarse.

## 5. Diseño del modelo de datos

Entidades: productos, lotes, proveedores, compras, detalle de compras, ventas, detalle de ventas, clientes, usuarios, roles y permisos.

Relaciones principales:

- Un producto tiene muchos lotes.
- Un proveedor tiene muchas compras.
- Una compra tiene muchos detalles de compra.
- Un producto puede aparecer en muchos detalles de compra.
- Un cliente tiene muchas ventas.
- Una venta tiene muchos detalles de venta.
- Cada detalle de venta identifica el producto y el lote dispensado.

### Diccionario de datos

#### lots

| Campo | Tipo | Descripción | Restricciones |
|---|---|---|---|
| id | BIGINT UNSIGNED | Identificador del lote | PK |
| product_id | BIGINT UNSIGNED | Producto asociado | FK, obligatorio |
| lot_number | VARCHAR(100) | Número del lote | Obligatorio, único por producto |
| expiration_date | DATE | Fecha de vencimiento | Obligatorio |
| quantity | INT UNSIGNED | Existencias del lote | No negativo, predeterminado 0 |
| created_at | TIMESTAMP | Fecha de creación | Nullable |
| updated_at | TIMESTAMP | Fecha de actualización | Nullable |

#### purchase_details

| Campo | Tipo | Descripción | Restricciones |
|---|---|---|---|
| id | BIGINT UNSIGNED | Identificador | PK |
| purchase_id | BIGINT UNSIGNED | Orden de compra | FK, obligatorio |
| product_id | BIGINT UNSIGNED | Producto comprado | FK, obligatorio |
| quantity | INT UNSIGNED | Cantidad solicitada | Mayor que cero |
| unit_cost | DECIMAL(10,2) | Costo unitario | No negativo |

#### sale_details

| Campo | Tipo | Descripción | Restricciones |
|---|---|---|---|
| id | BIGINT UNSIGNED | Identificador | PK |
| sale_id | BIGINT UNSIGNED | Venta relacionada | FK, obligatorio |
| product_id | BIGINT UNSIGNED | Producto vendido | FK, obligatorio |
| lot_id | BIGINT UNSIGNED | Lote dispensado | FK, obligatorio |
| quantity | INT UNSIGNED | Cantidad del lote | Mayor que cero |
| unit_price | DECIMAL(10,2) | Precio unitario | No negativo |

### Trazabilidad

El lote de cada unidad vendida se identifica en `sale_details.lot_id`. Si una venta utiliza varios lotes, se registra una fila por lote. Esto permite conocer qué lote se entregó y facilita los procesos de trazabilidad y retiro sanitario.

## 6. Matriz de seguridad

| Módulo / Acción | Admin | Regente | Cajero | Auxiliar |
|---|---|---|---|---|
| Productos: ver | Sí | Sí | Sí | Sí |
| Productos: crear/editar/eliminar | Sí | No | No | No |
| Compras: ver | Sí | Sí | No | Sí |
| Compras: crear/editar | Sí | No | No | No |
| Compras: eliminar | No | No | No | No |
| Recepción: recibir lotes | No | Sí | No | No |
| Bodega: ubicar mercancía | No | No | No | Sí |
| Inventario: ver | Sí | Sí | Sí | Sí |
| Ventas: ver | Sí | Sí | Sí | No |
| Ventas: crear | No | Sí | Sí | No |
| Ventas: editar/eliminar | No | No | No | No |
| Control especial: autorizar | No | Sí | No | No |
| Reportes: ver | Sí | No | No | No |
| Usuarios: gestionar | Sí | No | No | No |

### Segregación de funciones

El administrador crea las órdenes de compra, pero no puede recibir lotes. El regente verifica y recibe los medicamentos, pero no crea órdenes de compra. Así se reduce el riesgo de compras y recepciones realizadas sin control independiente.

### Regente que trabaja como cajero los sábados

Asignaría un segundo rol de cajero al regente, en lugar de permisos directos. Al iniciar sesión, elegiría el rol operativo correspondiente al turno: regente o cajero.

El sistema debe validar el rol activo en cada operación. Tener ambos roles asignados no debe permitir acumular automáticamente permisos de funciones incompatibles.
