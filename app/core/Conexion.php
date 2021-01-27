<?php
require_once(dirname(__DIR__).'/config/DatosConexion.php');
class Conexion
{
    //FUNCION CONECTAR
    public static function conectar()
    {
        $configurar = null;
        $configurar = array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8');
        try {
            $conexion = null;
            $conexion = new PDO(
                "mysql:host=" . SERVIDOR . "; dbname=" . NOMBRE_BD,
                USUARIO,
                PASSWORD,
                $configurar
            );
            $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $conexion;
        } catch (PDOException $e) {
            die("El error de la conexión fue: " . $e->getMessage());
        }
    }
}
