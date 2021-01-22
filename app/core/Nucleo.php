<?php
require_once('Conexion.php');
class Nucleo
{
    //ALMACENA LA CONEXION A LA BASE DE DATOS
    private $conexion;
    //TABLA EN LA QUE SE TRABAJARA
    public $tabla = '';
    //IDENTIFICA SI EL DESARROLLADOR NECESITA SABER EL ID 
    //DE UN REGISTRO QUE EL NUCLEO RECIEN HA HECHO
    public $regresameID = false;
    //ALMACENA LA SQL DEL DESARROLLADOR, PARA SER UTILIZADA POSTERIORMMENTE
    public $queryPersonalizado = '';
    //VARIABLE UTILIZADA PARA ALMACENAR EL TOTAL DE CAMPOS EN UNA TABLA DE LA BASE DE DATOS
    private $totalCampos = 0;
    //VARIABLES RELACIONADAS A LA BABOSA
    private $transaccionSQL = '';
    //CONSTRUCTOR DE LA CLASE
    public function __construct()
    {
        $obj = new Conexion();
        $this->conexion = $obj->conectar();
    }
    //SECCION DE INSERCION
    public function insertarRegistro(array $datos)
    {
        return $this->nuevoRegistro($datos);
    }
    //INGRESA DATOS 
    private function nuevoRegistro(array $campos)
    {
        try {
            if (!empty($this->tabla)) {
                if (empty($this->transaccionSQL)) {
                    $this->totalCampos = count($campos);
                    if (!empty($this->queryPersonalizado)) {
                        $values = "";
                        for ($i = 0; $i < $this->totalCampos; $i++) {
                            $values = $values . "?, ";
                        }
                        $values = substr($values, 0, -2);
                        $this->transaccionSQL = "INSERT INTO $this->tabla (" . $this->queryPersonalizado . ") VALUES(" . $values . ");";
                    } else {
                        $this->getInToMariaDB();
                    }
                }
                if (!empty($this->transaccionSQL)) {
                    $respuesta = false;
                    $resultado = $this->conexion->prepare($this->transaccionSQL);
                    $resultado->execute($campos);
                    if ($resultado->rowCount() > 0) {
                        $id = $this->conexion->lastInsertId();
                        $respuesta = $this->regresameID ? $id : true;
                    }
                    $resultado->closeCursor();
                    return $respuesta;
                }
            }
            return false;
        } catch (PDOException $e) {
            die("El error de la conexión fue: " . $e->getMessage());
        }
    }
    //OBTIENE LA SENTENCIA SQL INSERT IN TO 
    private function getInToMariaDB()
    {
        try {
            if (!empty($this->tabla)) {
                $consulta = "show columns from $this->tabla";
                $resultado = $this->conexion->prepare($consulta);
                if ($resultado->execute()) {
                    $salida = array();
                    $parametros = "";
                    $datos = $resultado->fetchAll(PDO::FETCH_ASSOC);
                    $totalColumnas = count($datos);
                    if ($totalColumnas >= $this->totalCampos) {
                        for ($i = ($totalColumnas - ($this->totalCampos)); $i < $totalColumnas; $i++) {
                            $salida[] = $datos[$i]['Field'];
                            $parametros = $parametros . "? ,";
                        }
                        $resultado->closeCursor();
                        $parametros = substr($parametros, 0, -1);
                        $this->transaccionSQL =  "INSERT INTO $this->tabla (" . implode(",", $salida) . ") VALUES (" . $parametros . ");";
                    }
                    $resultado->closeCursor();
                }
            }
        } catch (PDOException $e) {
            die("El error de la conexión fue: " . $e->getMessage());
        }
    }
    //SECCION DE MODIFICACION
    public function modificarRegistro(array $datos)
    {
        return $this->actualizar($datos);
    }
    private function actualizar(array $campos)
    {
        try {
            if (!empty($this->tabla)) {
                $this->totalCampos = count($campos);
                $resultado = null;
                if (empty($this->queryPersonalizado)) {
                    if (empty($this->transaccionSQL)) {
                        $this->getUpDateMariaDB($campos);
                    }
                    $resultado = $this->conexion->prepare($this->transaccionSQL);
                    for ($i = 1; $i < count($campos); $i++) {
                        $resultado->bindValue($i, $campos[$i]);
                    }
                    $resultado->bindValue($i, $campos[0]);
                } else {
                    $this->transaccionSQL = "UPDATE $this->tabla SET " . $this->queryPersonalizado;
                    $resultado = $this->conexion->prepare($this->transaccionSQL);
                    for ($i = 0; $i < count($campos); $i++) {
                        $resultado->bindValue($i + 1, $campos[$i]);
                    }
                }
                $respuesta = false;
                if ($resultado->execute()) {
                    $respuesta = true;
                }
                $resultado->closeCursor();
                return $respuesta;
            }
            return false;
        } catch (PDOException $e) {
            die("El error de la conexión fue: " . $e->getMessage());
        }
    }
    //OBTIENE EL SQL UPDATE 
    private function getUpDateMariaDB($campos)
    {
        try {
            if (!empty($this->tabla)) {
                $consulta = "show columns from $this->tabla";
                $resultado = $this->conexion->prepare($consulta);
                if ($resultado->execute()) {
                    $parametros = "";
                    $datos = $resultado->fetchAll(PDO::FETCH_ASSOC);
                    $totalColumnas = count($datos);
                    if ($totalColumnas == $this->totalCampos) {
                        for ($i = 1; $i < $totalColumnas; $i++) {
                            $parametros = $parametros . $datos[$i]['Field'] . "= ?, ";
                        }
                        $parametros = substr($parametros, 0, -2);
                        $this->transaccionSQL = "UPDATE $this->tabla SET " . $parametros . " WHERE {$datos[0]['Field']} = ?;";
                    }
                    $resultado->closeCursor();
                }
            }
        } catch (PDOException $e) {
            die("El error de la conexión fue: " . $e->getMessage());
        }
    }
    //SECCION DE LISTAR 
    public function getDatos()
    {
        return $this->obtenerData();
    }
    //OBTIENE TODOS LOS REGISTROS DE LA TABLA INDICADA
    private function obtenerData()
    {
        try {
            if (!empty($this->tabla)) {
                if (!empty($this->queryPersonalizado)) {
                    $this->transaccionSQL = $this->queryPersonalizado;
                } else {
                    $this->transaccionSQL = "SELECT * FROM $this->tabla";
                }
                $resultado = $this->conexion->prepare($this->transaccionSQL);
                $resultado->execute();
                $datos = $resultado->fetchAll(PDO::FETCH_ASSOC);
                $resultado->closeCursor();
                if ($datos) {
                    return $datos;
                }
            }
            return null;
        } catch (PDOException $e) {
            die("El error de la conexión fue: " . $e->getMessage());
        }
    }
    //SECCION DE ELIMINACION
    public function eliminarTodo()
    {
        return $this->eliminaDatosTabla();
    }
    //ELIMINA TODOS LOS REGISTROS DE UNA TABLA
    private function eliminaDatosTabla()
    {
        try {
            if (!empty($this->tabla)) {
                $consulta = "DELETE  FROM  $this->tabla;";
                $resultado = $this->conexion->prepare($consulta);
                if ($resultado->execute() && $resultado->rowCount() > 0) {
                    $resultado->closeCursor();
                    return true;
                }
                $resultado->closeCursor();
            }
            return false;
        } catch (PDOException $e) {
            die("El error de la conexión fue: " . $e->getMessage());
        }
    }
    public function eliminarRegistro(array $campos)
    {
        return $this->eliminaPorQuery($campos);
    }
    //ELIMINA UN REGISTRO
    private function eliminaPorQuery(array $campos)
    {
        try {
            if (!empty($this->tabla) && !empty($this->queryPersonalizado) && !empty($campos)) {
                $consulta = "DELETE FROM  $this->tabla WHERE " . $this->queryPersonalizado;
                $resultado = $this->conexion->prepare($consulta);
                if ($resultado->execute($campos) && $resultado->rowCount() > 0) {
                    $resultado->closeCursor();
                    return true;
                }
                $resultado->closeCursor();
            }
            return false;
        } catch (PDOException $e) {
            die("El error de la conexión fue: " . $e->getMessage());
        }
    }
    //ELIMINA LOS DATOS DE LAS VARIABLES QUE SE USAN EN LA EJECUCION
    public function limpiarBabosa()
    {
        $this->eliminarCache();
    }
    private function eliminarCache()
    {
        $this->transaccionSQL = '';
        $this->limpiarBabosa = false;
        $this->queryPersonalizado = '';
        $this->regresameID = false;
        $this->totalCampos = 0;
    }
}
