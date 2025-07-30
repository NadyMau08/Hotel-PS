-- Script para cambiar los nombres de tablas y columnas a español
-- Ejecutar en phpMyAdmin o desde la línea de comandos MySQL

USE hotel_management;

-- Cambiar el nombre de la tabla 'users' a 'usuarios'
RENAME TABLE users TO usuarios;

-- Modificar la estructura de la tabla 'usuarios' (antes 'users')
-- Asumimos que las columnas actuales son: id, nombre (ya está en español), usuario o username, clave o password, rol o role

-- Si la columna se llama 'username', cambiarla a 'usuario'
ALTER TABLE usuarios CHANGE username usuario VARCHAR(50) NOT NULL;

-- Si la columna se llama 'password', cambiarla a 'clave'
ALTER TABLE usuarios CHANGE password clave VARCHAR(255) NOT NULL;

-- Si la columna se llama 'role', cambiarla a 'rol'
ALTER TABLE usuarios CHANGE role rol VARCHAR(20) NOT NULL;

-- Si la columna se llama 'name', cambiarla a 'nombre'
ALTER TABLE usuarios CHANGE name nombre VARCHAR(100) NOT NULL;

-- Cambiar otras tablas comunes en un sistema hotelero (descomenta y adapta según las tablas que existan)

-- Tabla de habitaciones
-- RENAME TABLE rooms TO habitaciones;
-- ALTER TABLE habitaciones CHANGE room_number numero_habitacion INT NOT NULL;
-- ALTER TABLE habitaciones CHANGE room_type tipo_habitacion VARCHAR(50) NOT NULL;
-- ALTER TABLE habitaciones CHANGE status estado VARCHAR(20) NOT NULL;
-- ALTER TABLE habitaciones CHANGE price precio DECIMAL(10,2) NOT NULL;

-- Tabla de clientes/huéspedes
-- RENAME TABLE customers TO clientes;
-- ALTER TABLE clientes CHANGE first_name nombre VARCHAR(50) NOT NULL;
-- ALTER TABLE clientes CHANGE last_name apellido VARCHAR(50) NOT NULL;
-- ALTER TABLE clientes CHANGE email correo VARCHAR(100) NOT NULL;
-- ALTER TABLE clientes CHANGE phone telefono VARCHAR(20) NOT NULL;

-- Tabla de reservaciones
-- RENAME TABLE bookings TO reservaciones;
-- ALTER TABLE reservaciones CHANGE booking_date fecha_reserva DATE NOT NULL;
-- ALTER TABLE reservaciones CHANGE check_in fecha_entrada DATE NOT NULL;
-- ALTER TABLE reservaciones CHANGE check_out fecha_salida DATE NOT NULL;
-- ALTER TABLE reservaciones CHANGE status estado VARCHAR(20) NOT NULL;
-- ALTER TABLE reservaciones CHANGE customer_id cliente_id INT NOT NULL;
-- ALTER TABLE reservaciones CHANGE room_id habitacion_id INT NOT NULL;

-- Tabla de pagos
-- RENAME TABLE payments TO pagos;
-- ALTER TABLE pagos CHANGE amount monto DECIMAL(10,2) NOT NULL;
-- ALTER TABLE pagos CHANGE payment_date fecha_pago DATE NOT NULL;
-- ALTER TABLE pagos CHANGE payment_method metodo_pago VARCHAR(50) NOT NULL;
-- ALTER TABLE pagos CHANGE booking_id reservacion_id INT NOT NULL;

-- IMPORTANTE: Si existen relaciones de clave foránea, es posible que necesites eliminarlas antes de renombrar 
-- y luego volver a crearlas después de los cambios.

-- Por ejemplo:
-- ALTER TABLE reservaciones DROP FOREIGN KEY reservaciones_ibfk_1;
-- ALTER TABLE reservaciones DROP FOREIGN KEY reservaciones_ibfk_2;
-- (después de renombrar las tablas y columnas)
-- ALTER TABLE reservaciones ADD CONSTRAINT reservaciones_ibfk_1 FOREIGN KEY (cliente_id) REFERENCES clientes (id);
-- ALTER TABLE reservaciones ADD CONSTRAINT reservaciones_ibfk_2 FOREIGN KEY (habitacion_id) REFERENCES habitaciones (id);

-- Una vez que hayas ejecutado estos cambios, también necesitarás actualizar tu código PHP
-- para reflejar los nuevos nombres de tablas y columnas
