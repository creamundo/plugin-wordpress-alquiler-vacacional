# Casa Trinidad Vacation Rental Manager

Plugin de WordPress para gestionar la disponibilidad, las solicitudes de reserva y la operativa de limpieza del chalet Casa Trinidad en Caños de Meca.

## Características principales

### Calendario público y captación de reservas
- Inserta el calendario interactivo en cualquier página mediante el shortcode `[casatrinidad_calendar]`.
- Carga estilos y scripts específicos para el frontal y conecta con el API REST del plugin para mostrar disponibilidad, precios y validar los rangos seleccionados antes de enviar la solicitud de reserva.
- Registra eventos de uso para alimentar el panel de estadísticas.

### Panel de administración
- Añade el menú **Casa Trinidad** en el escritorio con submenús para estadísticas, calendario de disponibilidad, solicitudes recibidas, reservas aprobadas, checklist de tareas y ajustes de configuración.
- Permite actualizar precios o bloquear rangos completos directamente sobre el calendario, aprobar o rechazar solicitudes y generar reservas confirmadas que bloquean el calendario y crean la orden de trabajo asociada.
- Ofrece una interfaz para crear y ordenar las tareas de checklist por estancia y por fase (entrada o salida).

### Gestión de limpieza y entregas
- Genera órdenes de trabajo con token único que se pueden compartir mediante una URL pública para que el personal externo reporte checklist, horas de limpieza, compras y servicios realizados.
- Guarda toda la información operativa (checklists, servicios, compras y horas) junto a la reserva y la pone a disposición del panel interno.
- Expone ajustes para definir precios por hora, porcentajes de impuestos y gestión, comisiones de plataformas, importes de entrega de llaves y limpieza de ropa de cama, así como el correo de la persona de apoyo.

### API REST
El namespace `ctvr/v1` incluye endpoints para:
- Consultar el calendario mensual y calcular precios de un rango.
- Registrar solicitudes de reserva públicas y registrar eventos de uso.
- Gestionar disponibilidad, solicitudes, reservas y checklists desde el panel (requiere permisos de `manage_options`).
- Consultar y actualizar órdenes de trabajo tanto desde el panel como desde la vista pública mediante token.

### Esquema de datos
Al activarse el plugin crea tablas personalizadas para disponibilidad diaria, solicitudes, reservas aprobadas, órdenes de trabajo, definiciones de checklist y eventos estadísticos.

## Requisitos
- WordPress 6.0 o superior con el API REST habilitado.
- PHP 7.4 o superior.

## Instalación
1. Copia la carpeta `casatrinidad-vacation-rental` dentro de `wp-content/plugins/` de tu instalación de WordPress.
2. Accede al escritorio y activa **Casa Trinidad Vacation Rental Manager**. Durante la activación se crearán las tablas necesarias en la base de datos.
3. (Opcional) Crea una página pública y añade el shortcode `[casatrinidad_calendar]` para mostrar el calendario.

## Configuración
1. Ve a **Casa Trinidad → Ajustes** y define:
   - Número mínimo de noches y correos de notificación.
   - Precios y porcentajes operativos: limpieza por hora, impuestos, comisiones de plataformas (en JSON), entrega de llaves, limpieza de ropa de cama y porcentaje de gestión.
   - Correo electrónico de la persona que apoya en limpieza para avisos manuales.
2. Ajusta el calendario inicial desde **Casa Trinidad → Disponibilidad** asignando precios o bloqueando días/rangos.
3. Configura el checklist de tareas en **Casa Trinidad → Checklist**.

## Flujo de trabajo recomendado
1. Los visitantes seleccionan fechas y completan el formulario del calendario público.
2. Las solicitudes aparecen en **Casa Trinidad → Solicitudes**, donde se pueden revisar, aprobar o rechazar.
3. Al aprobar una solicitud se crea una reserva confirmada, se bloquea el calendario y se genera un enlace público con token para la orden de trabajo.
4. Desde **Casa Trinidad → Reservas aprobadas** se consulta cada orden, se actualizan checklist, horas y gastos, y se obtiene el coste total de la operación.

## Desarrollo
- Los assets de JavaScript y CSS ya están empacados; no es necesario ejecutar un proceso de build adicional para usar el plugin.
- El código utiliza componentes de React proporcionados por WordPress (`wp-element`, `wp-components`, `wp-api-fetch`). Si necesitas modificarlos puedes adaptar los archivos dentro de `includes/assets/` y volver a empaquetarlos con tu herramienta preferida.
