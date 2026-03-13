<?php

var_dump($_POST);
$host = 'localhost';
$usuario = 'root';
$contraseña = '';
$base_de_datos = 'mibasededatos';

// Crear conexión
$conn = new mysqli($host, $usuario, $contraseña, $base_de_datos);

// Verificar conexión
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}
echo "Conexión exitosa";


$nombre=$_POST['nombre'];
$apellido=$_POST['apellido'];
$email=$_POST['email'];
$fecha=$_POST['fecha'];
$sexo=$_POST['sexo'];
$password=password_hash($_POST['password'],PASSWORD_DEFAULT);


$consulta="Insert into mibasededato('id', 'nombre','apellido','email','fecha','sexo','password') values('1','$nombre','$apellido','$email','$fecha','$sexo','$password')";

$resultado=$conexion->query($consulta);

echo $resultado;

$filas=mysql_num_rows($resultado);

echo $filas;
?>
