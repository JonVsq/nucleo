<?php
require_once('Conexion.php');
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
    //INDICA SI EL DESARROLLADOR DESEA CONSULTAR CAMPOS EXCEPTO A UN REGISTRO ESPECIFICO
    public $consultarModificar = false;
    //VARIABLE UTILIZADA PARA ALMACENAR EL TOTAL DE CAMPOS EN UNA TABLA DE LA BASE DE DATOS
    private $totalCampos = 0;
    //VARIABLES RELACIONADAS A LA BABOSA
    private $transaccionSQL = '';
    //VARIABLES PARA EL ENTORNO DE PAGINACION
    //TOTAL DE REGISTROS A MOSTRAR POR PAGINA
    public $porPagina = 10;
    //MAXIMO DE ENLACES PARA PAGINAS A GENERAR
    public $maximoEnlace = 4;

    //ALMACENA LA QUERY NECESARIA PARA CALCULAR EL TOTAL DE REGISTROS DE LA PAGINACION
    public $queryTotalRegistroPag;
    //ALMACENA LA QUERY NECESARIA PARA EXTRAER LOS REGISTROS DE LA PAGINACION
    public $queryExtractRegistroPag;
    //UTILIZADA PARA IDENTIFICAR EL NUMERO DE PAGINA QUE EL USUARIO SOLICITA
    public $numPagina;
    //UTILIZADA PARA ALMACENAR EL TOTAL DE PAGINAS QUE SE OBTENDRAN
    private $total_paginas;
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
    public function getDatosParametros($campos)
    {
        return $this->getDataParametros($campos);
    }
    //OBTIENE TODOS LOS REGISTROS DE LA TABLA INDICADA CON PARAMETROS
    private function getDataParametros(array $campos)
    {
        try {
            if (!empty($this->tabla)) {
                if (!empty($this->queryPersonalizado)) {
                    $this->transaccionSQL = $this->queryPersonalizado;
                    $resultado = $this->conexion->prepare($this->transaccionSQL);
                    $resultado->execute($campos);
                    $datos = $resultado->fetchAll(PDO::FETCH_ASSOC);
                    $resultado->closeCursor();
                    if ($datos) {
                        return $datos;
                    }
                }
            }
            return null;
        } catch (PDOException $e) {
            die("El error de la conexión fue: " . $e->getMessage());
        }
    }
    //SECCION DE LA PAGINACION
    public function getDatosHtml(array $campos, array $botones, $identificador)
    {
        return $this->generarTablaHtml($campos, $botones, $identificador);
    }

    //LISTAR DATOS
    public function generarTablaHtml(array $campos, $botones, $identificador)
    {
        try {
            $empezarDesde = $this->iniciarDesde($this->numPagina);
            $this->queryExtractRegistroPag = $this->queryExtractRegistroPag  . " LIMIT " . $empezarDesde  . ", "  . $this->porPagina;
            $totalRegistros = $this->numeroRegistros();
            $this->total_paginas = $this->numeroPaginas($totalRegistros);
            $datos = $this->consultarDatos();
            $tabla = "";
            //OBTIENE EL HTML A MOSTRAR
            foreach ($datos as $objeto) {
                $fila = "<tr class='text-center'>";
                foreach ($campos as $valor) {
                    $fila = $fila . "<td>{$objeto[$valor]}</td>";
                }
                if (!empty($botones)) {
                    $fila = $fila . "<td>";
                    foreach ($botones as $boton => $tipo) {
                        $fila = $fila . "<button obj$boton='{$objeto[$identificador]}'  type='button'  class='$boton btn btn-$tipo[1] btn-sm' $tipo[2] >
                                        <i class='fas fa-$tipo[0]' ></i></button>";
                    }
                    $fila = $fila . "</td>";
                }
                $fila = $fila . "</tr>\n";
                $tabla = $tabla . $fila;
            }

            $paginador = $this->enlaces();

            //ARRAY QUE SE ENVIARA A JS
            $data = array();
            $data["totalRegistros"] = $totalRegistros;
            $data["tabla"] = ($totalRegistros > 0) ? $tabla : "<td class='text-center text-info'>No hay registros para mostrar</td><td></td><td></td>";
            $data["paginador"] = $paginador;
            $data["totalPagina"] = $this->total_paginas;
            $data["paginaActual"] = ($totalRegistros > 0) ? $this->numPagina : 0;
            $data["desde"] = ($totalRegistros > 0) ? $empezarDesde + 1 : 0;
            if (($empezarDesde + $this->porPagina) > $totalRegistros) {
                $data["hasta"] = $totalRegistros;
            } else {
                $data["hasta"] = $empezarDesde + $this->porPagina;
            }
            return $data;
        } catch (PDOException $e) {
            die('Ocurrio un error: ' . $e->getMessage());
        }
    }
    //CALCULA DESDE DONDE SE EMPEZARA A TRAER LOS REGISTROS DE LA BASE DE DATOS
    private function iniciarDesde()
    {
        return ($this->numPagina - 1) * $this->porPagina;
    }
    //CALCULA EL NUMERO DE PAGINAS QUE SE VAN A MOSTRAR
    private function numeroPaginas($totalRegistros)
    {
        return ceil($totalRegistros / $this->porPagina);
    }

    //OBTIENE EL NUMERO DE REGISTROS EN LA BASE DE DATOS SEGUN QUERY
    private  function numeroRegistros()
    {
        try {
            $totalRegistros = 0;
            $resultado = $this->conexion->prepare($this->queryTotalRegistroPag);
            $resultado->execute();
            $totalRegistros = $resultado->fetchAll(PDO::FETCH_ASSOC);
            $resultado->closeCursor();
            $totalRegistros = $totalRegistros[0]["total"];
            settype($totalRegistros, 'int');
            return $totalRegistros;
        } catch (PDOException $e) {
            die("Ocurrio un error en la consulta: " . $e->getMessage());
        }
    }
    //CONSULTA LOS DATOS
    private function consultarDatos()
    {
        try {
            $resultado = $this->conexion->prepare($this->queryExtractRegistroPag);
            $resultado->execute();
            $datos = $resultado->fetchAll(PDO::FETCH_ASSOC);
            $resultado->closeCursor();
            return $datos;
        } catch (PDOException $e) {
            die("Ocurrio un error en la consulta: " . $e->getMessage());
        }
    }
    //GENERA LOS ENLACES DE LA PAGINACION
    private function enlaces()
    {
        $paginador = "";
        $indice = $this->numPagina;
        if ($this->total_paginas > $this->maximoEnlace) {
            if ($this->numPagina == 1) {
                $paginador = $paginador .  "<li  class='page-item disabled'><a class='page-link' href='#'>Anterior</a></li>";
            } else {
                $paginador = $paginador .  "<li pag = '" . ($this->numPagina - 1) . "' class='siguiente page-item'>
                                                <a class='page-link' href='#'>Anterior</a></li>";
            }
            if ($this->numPagina == 1) {
                $paginador = $paginador . "<li   class='page-item active'><a class='page-link disable' href='#'>1</a></li>";
            } else {
                $paginador = $paginador . "<li  pag = '1' class='pagina page-item'><a class='page-link' href='#'>1</a></li>";
            }
            if ((($this->total_paginas) - $this->numPagina < $this->maximoEnlace) && $this->numPagina >= $this->maximoEnlace) {
                $inicio = (($this->total_paginas) - $this->maximoEnlace);
                while ($this->maximoEnlace > 0) {
                    if ($inicio > $this->total_paginas) {
                        break;
                    } else {
                        if ($inicio != 1 && $inicio != $this->total_paginas) {
                            if ($inicio == $this->numPagina) {
                                $paginador = $paginador . "<li   class='page-item active'><a class='page-link disable' href='#'>{$inicio}</a></li>";
                            } else {
                                $paginador = $paginador . "<li  pag = '{$inicio}' class='pagina page-item'><a class='page-link' href='#'>{$inicio}</a></li>";
                            }
                            $this->maximoEnlace--;
                            $indice++;
                        }
                    }
                    $inicio++;
                }
            } else {
                for ($i = $this->numPagina; $i <= $this->total_paginas; $i++) {
                    if ($this->maximoEnlace == 0) {
                        break;
                    }
                    if ($i != 1 && $i != $this->total_paginas) {
                        if ($i == $this->numPagina) {
                            $paginador = $paginador . "<li   class='page-item active'><a class='page-link disable' href='#'>{$i}</a></li>";
                        } else {
                            $paginador = $paginador . "<li  pag = '{$i}' class='pagina page-item'><a class='page-link' href='#'>{$i}</a></li>";
                        }
                        $this->maximoEnlace--;
                    }
                    $indice++;
                }
            }
            if ($this->numPagina == $this->total_paginas) {
                $paginador = $paginador . "<li   class='page-item active'><a class='page-link disable' href='#'>$this->total_paginas</a></li>";
            } else {
                $paginador = $paginador . "<li  pag = '$this->total_paginas' class='pagina page-item'><a class='page-link' href='#'>$this->total_paginas</a></li>";
            }
            if ($indice > $this->total_paginas) {
                $paginador = $paginador .  "<li  class='page-item disabled'>
                                             <a class='page-link' href='#'>Siguiente</a></li>";
            } else {
                $paginador = $paginador .  "<li pag = '" . ($indice) . "' class='siguiente page-item'>
                                             <a class='page-link' href='#'>Siguiente</a></li>";
            }
        } else {
            $paginador = $paginador .  "<li  class='page-item disabled'>
                                         <a class='page-link' href='#'>Anterior</a></li>";
            for ($i = 1; $i <= $this->total_paginas; $i++) {
                if ($i == $this->numPagina) {
                    $paginador = $paginador . "<li   class='page-item active'><a class='page-link disable' href='#'>{$i}</a></li>";
                } else {
                    $paginador = $paginador . "<li  pag = '{$i}' class='pagina page-item'><a class='page-link' href='#'>{$i}</a></li>";
                }
            }
            $paginador = $paginador .  "<li  class='page-item disabled'>
                                         <a class='page-link' href='#'>Siguiente</a></li>";
        }
        return $paginador;
    }
    //SECCION DE CONSULTAS
    public function coincidencias($datos, $identificador, $valorIdentificador)
    {
        return  $this->consultarCoincidencias($datos, $identificador, $valorIdentificador);
    }
    private function consultarCoincidencias($datos, $identificador, $id)
    {
        try {
            $respuesta = array();
            $existe = 0;
            foreach ($datos as $campo => $valor) {
                $consulta = "";
                if ($this->consultarModificar) {
                    $consulta = "SELECT COUNT($identificador) AS total  FROM $this->tablaBase WHERE $identificador<> :id AND $campo = :valor $this->and";
                    $resultado = $this->conexion->prepare($consulta);
                    $resultado->bindValue(':valor', $valor);
                    $resultado->bindValue(':id', $id);
                } else {
                    $consulta = "SELECT COUNT($identificador) AS total  FROM $this->tablaBase WHERE $campo = :valor $this->and";
                    $resultado = $this->conexion->prepare($consulta);
                    $resultado->bindValue(':valor', $valor);
                }

                $resultado->execute();
                $registros = $resultado->fetchAll(PDO::FETCH_ASSOC);
                $registros = $registros[0]["total"];
                settype($registros, 'int');
                if ($existe < 1) {
                    $existe = $registros > 0 ? 1 : 0;
                }
                $respuesta[] = array(
                    $campo => $registros
                );
            }
            $respuesta["existe"] = $existe;
            $resultado->closeCursor();
            return $respuesta;
        } catch (PDOException $e) {
            die('Ocurrio un error: ' . $e->getMessage());
        }
    }
    //ELIMINA LOS DATOS DE LAS VARIABLES QUE SE USAN EN LA EJECUCION
    public function limpiarBabosa()
    {
        $this->eliminarCache();
    }
    private function eliminarCache()
    {
        $this->consultarModificar = false;
        $this->transaccionSQL = '';
        $this->limpiarBabosa = false;
        $this->queryPersonalizado = '';
        $this->regresameID = false;
        $this->totalCampos = 0;
    }
    /**
     * Get the value of Conexion
     */
    public function getConexion()
    {
        return $this->conexion;
    }
}
