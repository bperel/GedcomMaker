<?php
require_once('ComplexObject.class.php');

class ObjectArray extends ComplexObject{
    var $objects=array();

    function ObjectArray() {
        list($this->objects)=func_get_args();
    }
    
    function mettre_dans_retour() {
        foreach($this->objects as $object)
            $object->mettre_dans_retour();
    }
}
?>
