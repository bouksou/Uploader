<?php

namespace Uploader\Model\Behavior;

use Cake\Event\Event;
use Cake\ORM\Behavior;
use Cake\ORM\Table;
use Cake\ORM\Entity;
use Cake\ORM\Query;
use Cake\Utility\Inflector;

use Imagine\Gd\Imagine;
use Imagine\Image\Box;

class UploaderBehavior extends Behavior {

    protected $_defaultConfig = [
        'fields' => []
    ];

    public $options = array();

    protected $_table;

    public function __construct(Table $table, array $config = []) {

        parent::__construct($table, $config);

        $this->options = array_merge($this->_defaultConfig, $config);
        $this->_table = $table;
    }

    public function fileExtension($value, $extensions, $context, $allowEmpty = true){
        if($allowEmpty && empty($value['tmp_name'])){
            return true;
        }

        $extension = strtolower(pathinfo($value['name'], PATHINFO_EXTENSION));

        return in_array($extension, $extensions);
    }

    public function afterSave(Event $event, Entity $entity) {
        foreach ($this->options['fields'] as $field => $path) {
            if(!empty($entity->get($field.'_file')['name'])){
                $id = $entity->get('id');
                $file = $entity->get($field.'_file');
                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                $path = $this->getFilePath($entity, $path, $extension);
                $dirname = dirname($path);

                if(!file_exists(WWW_ROOT . $dirname)){
                    mkdir(WWW_ROOT . $dirname, 0777, true);
                }

                $this->deleteUpload($entity, $field);

                move_uploaded_file(
                    $file['tmp_name'],
                    WWW_ROOT . $path
                );


                foreach ($this->options['sizes'] as $size) {
                    $this->resize($id, $path, $size['width'], $size['height'], $extension);
                }

                chmod(WWW_ROOT . $path, 0777);

                $this->_table->updateAll([
                        $field => $path
                    ],[
                        'id' => $id
                    ]
                );
            }                 
        }
            
    }

    public function beforeDelete(Event $event, Entity $entity){       
        foreach ($this->options['fields'] as $field => $path) {
            $this->deleteUpload($entity, $field);
        }

        return true;
    }

    private function deleteUpload(Entity $entity, $field){
        $folder = $entity->get($field);
        if(file_exists($folder)){
            $folder = dirname(WWW_ROOT. $folder);
            $this->deleteDir($folder);          
        }
    }

    public function getFilePath(Entity $entity, $path, $extension){
        $id = $entity->get('id');
        $path = trim($path, '/');
        $replace = [
            '%id1000'   => ceil($id / 1000),
            '%id100'    => ceil($id / 100),
            '%id'       => $id,
            '%y'        => date('y'),
            '%m'        => date('m')
        ];

        $path = strtr($path, $replace) . '.' . $extension;

        return $path;

    }

    private function deleteDir($dir) {
        if (!file_exists($dir)) {
            return true;
        }

        if (!is_dir($dir)) {
            return unlink($dir);
        }

        return $this->rmdirectory($dir);
    }

    private function rmdirectory($dir)
    {
        $files = glob($dir.'/*'); // get all file names
        foreach($files as $file){ // iterate files
          if(is_file($file))
            unlink($file); // delete file
        }
    }

    private function resize($id, $path, $width, $height, $extension){
        $imagine = new imagine();
        $size = new Box($width, $height);

        $imagine->open($path)->thumbnail($size, 'outbound')->save(WWW_ROOT . dirname($path) . DS . $id .'_'. $width .'x'. $height .'.'.$extension, ['jpeg_quality' => 90]);
    }
}

?>