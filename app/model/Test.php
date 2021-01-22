<?php
require_once('../core/Nucleo.php');
class Test
{
    private $nucleo;
    public function __construct()
    {
        $this->nucleo = new Nucleo();
        $this->nucleo->tabla = 'persona';
    }
    public function create(array $datos)
    {
        return $this->nucleo->insertarRegistro($datos);
    }
    public function update(array $datos)
    {
        return $this->nucleo->modificarRegistro($datos);
    }
    public function getData()
    {
        return $this->nucleo->getDatos();
    }
    public function eliminarTodo()
    {
        return $this->nucleo->eliminarTodo();
    }
    public function eliminarRegistro(array $datos)
    {
        $this->nucleo->queryPersonalizado='id = 7';
        return $this->nucleo->eliminarRegistro($datos);
    }
}
