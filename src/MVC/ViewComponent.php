<?php

namespace everest\MVC;


use everest\MVC\TemplateEngine\Engine;
use everest\MVC\TemplateEngine\FileSystemLoader;

class ViewComponent extends MinifyHelper
{

    protected $registeredVariables = [];
    protected $componentFile;

    public function invoke(){

        if($this->componentFile === null){
            $this->componentFile = $this->getViewComponentDefaultView();
        }

        $razr = new Engine(new FilesystemLoader(), BASE_DIR . "/.cache" );

        echo $razr->render($this->componentFile, $this->registeredVariables);
    }


    protected function getViewComponentDefaultView(){

        $function = new \ReflectionClass($this);
        $realClass = $function->getShortName();

        //TestViewComponent => components/test.php

        return  "components" . DIRECTORY_SEPARATOR . strtolower(explode("ViewComponent",$realClass)[0]) . VIEWS_EXTENSION;

    }


}