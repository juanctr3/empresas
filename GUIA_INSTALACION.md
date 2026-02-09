# Guía de Instalación: CoticeFacil (Parte 1) 🚀

Esta guía está diseñada para que cualquier persona, incluso sin conocimientos técnicos previos, pueda poner en marcha el sistema en un servidor VPS con **aaPanel**.

## Paso 1: Configurar la Base de Datos en aaPanel

1. Ingresa a tu panel de control **aaPanel**.
2. Haz clic en el menú **"Database"** en la barra lateral izquierda.
3. Haz clic en el botón azul **"Add database"**.
4. Completa el formulario:
   - **DBName:** `coticefacil-db`
   - **UserName:** `cotice-user`
   - **Password:** `cotice_temp_123`
5. Presiona **"Submit"**.
6. En la lista de bases de datos, busca la que acabas de crear y haz clic en **"Import"**.
7. Selecciona **"Add local file"**, sube el archivo `database.sql` que te entregué y luego haz clic en **"Import"** nuevamente para ejecutarlo.

## Paso 2: Subir los Archivos al Servidor

1. Ve al menú **"Website"** en aaPanel.
2. Crea un nuevo sitio web o haz clic en la carpeta del sitio donde quieres instalarlo para abrir el **"File Manager"**.
3. Sube la carpeta del proyecto o los archivos individuales:
   - `index.php`
   - `db.php`
   - `productos.php`
   - `configuracion.php`
   - Carpeta `includes/` (con `header.php` y `footer.php`)
4. Asegúrate de que los permisos de los archivos sean correctos (generalmente `755` para carpetas y `644` para archivos, aaPanel lo hace por defecto).

## Paso 3: Vincular los archivos con la Base de Datos

1. Dentro del administrador de archivos de aaPanel, abre el archivo `db.php`.
2. Busca las siguientes líneas y cámbialas por los datos que creaste en el **Paso 1**:
   ```php
   $db_user = 'AQUÍ_TU_USUARIO';
   $db_pass = 'AQUÍ_TU_CONTRASEÑA';
   ```
3. Guarda los cambios.

## Paso 4: ¡Listo para usar!

1. Abre tu navegador y escribe la dirección de tu dominio o la IP de tu VPS.
2. ¡Felicidades! Ya puedes empezar a configurar tus **Impuestos**, **Unidades** y **Productos**.

---

### Notas de Diseño
- **Mobile-First:** El sistema está diseñado para usarse cómodamente desde un smartphone.
- **Modernidad:** Se utiliza la fuente 'Inter' y una paleta de colores limpia estilo Apple/Stripe.
- **Escalabilidad:** Esta es la Parte 1. La base de datos ya está preparada para crecer con más funcionalidades.
