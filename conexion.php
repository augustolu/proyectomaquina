<?php
$host = "localhost";
$usuario = "root";
$password = "";
$base_datos = "urkupina";

$conn = new mysqli($host, $usuario, $password, $base_datos);

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}



<div class="row g-4">
<?php while($fila = $resultado->fetch_assoc()) { ?>
    <div class="col-md-4">
        <div class="card h-100">
            <img src="<?php echo $fila['imagen']; ?>" class="card-img-top" style="height:250px; object-fit:cover;">
            <div class="card-body">
                <h5><?php echo $fila['nombre']; ?></h5>
                <p>$<?php echo $fila['precio']; ?></p>
                <p><?php echo $fila['descripcion']; ?></p>
            </div>
        </div>
    </div>
<?php } ?>
</div>
?>