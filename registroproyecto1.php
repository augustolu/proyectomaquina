<?php
$host = "localhost";
$usuario = "root";
$contraseña = "";
$basededatos = "mi_base_de_datos";

// Conexión a la base de datos
$conexion = new mysqli($host, $usuario, $contraseña, $basededatos);

if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

// Recibir datos del formulario
$nombre = $_POST['nombre'];
$apellido = $_POST['apellido'];
$email = $_POST['email'];
$fecha = $_POST['fecha'];
$sexo = $_POST['sexo'];
$password = password_hash($_POST['password'], PASSWORD_DEFAULT);

// Verificar si el email ya existe
$verificar = $conexion->prepare("SELECT id FROM usuarios WHERE email = ?");
$verificar->bind_param("s", $email);
$verificar->execute();
$verificar->store_result();

if ($verificar->num_rows > 0) {
    echo "El email ya está registrado";
} else {
    $sql = "INSERT INTO usuarios (nombre, apellido, email, fecha, sexo, password) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("ssssss", $nombre, $apellido, $email, $fecha, $sexo, $password);

    if ($stmt->execute()) {
        echo "Registro exitoso ✅";
    } else {
        echo "Error al registrar: " . $stmt->error;
    }

    $stmt->close();
}

$verificar->close();
$conexion->close();
?>