# PowerTranz WooCommerce Gateway

Este plugin integra la pasarela de pagos **PowerTranz** en WooCommerce, soportando pagos seguros con 3D-Secure y una interfaz de usuario mejorada.

## Características

*   **Integración Directa (SPI):** Los clientes permanecen en tu sitio durante el checkout.
*   **Soporte 3D-Secure:** Manejo completo de flujos de autenticación bancaria (Visa Secure, MasterCard Identity Check).
*   **Interfaz Mejorada:**
    *   Detección automática de marca de tarjeta (Visa, MasterCard, Amex).
    *   Formato automático de números y fechas.
    *   Validación de entrada en tiempo real.
*   **Seguridad:**
    *   Datos sensibles enviados directamente vía SSL.
    *   Campos protegidos (CVV oculto).
    *   **Fail-Safe Endpoint:** Sistema robusto para manejar redirecciones bancarias evitando conflictos con temas/plugins.

## Instalación

1.  Descarga el código o clona este repositorio en `wp-content/plugins/`.
2.  Activa el plugin desde el panel de WordPress.
3.  Ve a **WooCommerce > Ajustes > Pagos > PowerTranz**.
4.  Configura tus credenciales (Merchant ID y Password).

## Configuración de Pruebas (Staging)

El plugin viene pre-configurado para conectar con el entorno de staging de PowerTranz.
Asegúrate de seleccionar "Entorno: Pruebas" en la configuración.

## Requisitos

*   PHP 7.4 o superior.
*   WooCommerce 5.8 o superior.
*   Certificado SSL instalado en el sitio.

## Estructura del Proyecto

*   `includes/`: Lógica principal y clases de la pasarela.
*   `assets/`: Archivos CSS, JS e imágenes (SVGs).
*   `templates/`: (Opcional) Plantillas de vista.

## Autor

Desarrollado para integración con PowerTranz Gateway.
