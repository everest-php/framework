<?php

namespace hooks\MVC;

use hooks\Storage\FileSystem;
use hooks\Storage\Globals;
use hooks\Storage\Session;

class Route{

    private $routes = [];

    public function cleanURI(){
        if(strpos($this->projectURI(), "//")){
            $cleanURI = str_replace("//", "/", $this->projectURI());
            redirect()->to($cleanURI);
        }
    }

    public function deliver(string $uri = null){


        $this->cleanURI();

        $uri = $uri ?? $this->projectURI();

        if($this->routeCustomRoutes($uri)){
            return;
        }

        if($this->routeMVC($uri)){
            return;
        }


        Redirect::trigger(404);
    }

    private function routeCustomRoutes(string $uri) : bool{

        foreach ($this->routes as $alias => $path){

            $alias = trim($alias,"/");

            //Step 1 of 2: Matching Hard-Codes URLS
            if($alias == $uri){
                $this->routeMVC($path);
                return true;
            }

            //Step 2 of 2: Matching Patterns

            if($this->match($alias,$uri)){
                return true;
            }

        }

        return false;
    }

    public function routeMVC(string $path, array $vars = []) : bool{

        $path = trim($path,"/");
        $paths = explode("/",$path);

        // If last parameter of a URL is a number, inject it as a parameter id
        $uri_end = end($paths);
        if(is_numeric($uri_end)) {
            array_pop($paths);
            $vars["id"] = $uri_end;
        }

        if(count($paths) >= 2) {

            /*
             +--------------------------------------------------
             + Case 1 : If URL is complete with method names
             +
           */

            $method = end($paths);

            $controller = ucfirst($paths[0]);
            for($i = 1; $i < count($paths) - 1; $i++){
                $controller .= "\\" . ucfirst($paths[$i]);
            }

            if($this->controllerExists($controller, $method)){

                Globals::setItem("controller",$controller);
                Globals::setItem("method", $method);

                $this->injectMVC($controller,$method,$vars);
                return true;
            }


            /*
             +--------------------------------------------------
             + Case 2 : If URL is needs index path
             +
           */

            $method = "index";

            $controller = ucfirst($paths[0]);
            for($i = 1; $i < count($paths); $i++){
                $controller .= "\\" . ucfirst($paths[$i]);
            }

            if($this->controllerExists($controller, $method)){

                Globals::setItem("controller",$controller);
                Globals::setItem("method", $method);

                $this->injectMVC($controller,$method,$vars);
                return true;
            }






        } else if(count($paths) === 1){
            $controller = $paths[0];
            $method = $_SERVER["REQUEST_METHOD"];

            if($this->controllerExists($controller, $method)){

                Globals::setItem("controller",$controller);
                Globals::setItem("method", $method);

                $this->injectMVC($controller,$method,$vars);
                return true;
            }

            $method = "index";
            if($this->controllerExists($controller, $method)){

                Globals::setItem("controller",$controller);
                Globals::setItem("method", $method);

                $this->injectMVC($controller,$method,$vars);
                return true;
            }

        } else {
            $controller = "home";
            $method = $_SERVER["REQUEST_METHOD"];

            if($this->controllerExists($controller, $method)){

                Globals::setItem("controller",$controller);
                Globals::setItem("method", $method);

                $this->injectMVC($controller,$method,$vars);
                return true;
            }

            $method = "index";
            if($this->controllerExists($controller, $method)){

                Globals::setItem("controller",$controller);
                Globals::setItem("method", $method);

                $this->injectMVC($controller,$method,$vars);
                return true;
            }

        }

        return false;
    }

    private function controllerExists($controller, $method){
        if (FileSystem::exists(BASE_DIR . "/Controllers/" . ucfirst($controller) . "Controller.php" )){

            $controllerPath = "Controllers\\" . ucfirst($controller) . "Controller";

            if(method_exists($controllerPath,$method)){
                return true;
            }

            $method = $this->hyphenToCammelCase($method);
            if(method_exists($controllerPath,$method)){
                return true;
            }
        }
        return false;
    }

    private function prepareMethodParameters($vars) {
        if ($_SERVER['REQUEST_METHOD'] === 'GET' || $_SERVER['REQUEST_METHOD'] === 'DELETE') {
            $vars = array_merge($vars, $_GET);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'PATCH') {
            if (strpos($_SERVER["CONTENT_TYPE"],'json') !== false) {
                $model = json_decode(file_get_contents("php://input"));
                $vars["model"] = $model;
            } else {
                $vars["model"] = $_POST;
            }
        }

        return $vars;
    }

    private function injectMVC($controller, $method, $vars){

        $vars = $this->prepareMethodParameters($vars);

        $controller = "Controllers\\" . ucfirst($controller) . "Controller";
        $method = str_replace("-", "", $method);

        // 1. Method Matches the name
        if (method_exists($controller,$method)) {
            $this->injectControllerMethodWithParams($controller, $method, $vars);
        } else {
            Redirect::trigger(500);
        }
    }

    private function injectControllerMethodWithParams($controller, $method, $vars) {
        $reflectionClass = new \ReflectionClass($controller);
        $refectionMethod = $reflectionClass->getMethod($method);
        $controllerInstance = $reflectionClass->newInstance();

        $inject = array();
        foreach ($refectionMethod->getParameters() as $p) {
            $name = $p->name;
            if (!isset($vars[$name])) {
                $vars[$name] = null;
                continue;
            }

            $parameterType = $p->getClass();
            if ($parameterType == null) {
                $inject[$name] = $vars[$name];
            } else {
                $className = $parameterType->name;
                $obj = new $className;
                foreach ((array) $vars[$name] as $k => $v) {
                    $obj->$k = $v;
                }
                $inject[$name] = $obj;
            }
        }

        $result = $refectionMethod->invokeArgs($controllerInstance, $inject);
        $this->renderComputerMethod($result);
    }

    private function renderComputerMethod($view) {
        if( is_object($view) && get_class($view) === "hooks\\MVC\\View"){
            $view->render();
        } else {
            if (strpos($_SERVER["HTTP_ACCEPT"], 'json') !== false) {
                echo json_encode($view, JSON_PRETTY_PRINT);
            } else {
                print_r($view);
            }
        }
    }

    private function match($alias, $uri){

        if($this->isValidAliasForRegEx($alias, $uri)){


            $variablesToFetchFromURL = $this->variablesToFetchFromURL($alias);

            if(count($variablesToFetchFromURL) > 0){

                $_alias = $alias;
                foreach($variablesToFetchFromURL as $var){
                    $_alias = str_replace( "{".  $var. "}","$",$_alias);
                }
                $variablesFetched = $this->extractVariablesFromURL($_alias,$uri);


                $matchedVariables = [];

                if(count($variablesFetched) === count($variablesToFetchFromURL)){
                    for($i = 0; $i < count($variablesFetched); $i++){
                        $variableName = $variablesToFetchFromURL[$i];
                        $variableValue = $variablesFetched[$i];
                        $matchedVariables[$variableName] = $variableValue;
                    }

                    $aliasedMVCRoute = $this->routes[$alias];

                    return $this->routeMVC($aliasedMVCRoute, $matchedVariables);
                }
            }


        }

        return false;
    }

    private function isValidAliasForRegEx($alias, $uri){
        return
            $alias !== "" &&                                                //Not Empty
            substr_count($uri, "/") ===  substr_count($alias, "/") &&       //Both have equal /
            substr_count($alias, "{") !== 0 &&                              //Has at least one {
            (substr_count($alias, "{") == substr_count($alias, "}"));       //Has equal number of { and }


    }

    private function variablesToFetchFromURL($alias){
        $expression = "/{([a-zA-Z0-9-:]+)}/";
        preg_match_all($expression, $alias, $matches);

        return $matches[1];
    }

    private function extractVariablesFromURL($pattern,$input){
        $delimiter = rand();
        while (strpos($input,$delimiter) !== false) {
            $delimiter++;
        }

        $exps = explode("$",$pattern);
        foreach($exps as $exp){
            $input = str_replace($exp,",", $input);
        }

        $responses = explode(",", $input);
        array_shift($responses);
        return $responses;
    }

    public function setRoutes($routes)
    {
        $this->routes = $routes;
        return $this;
    }

    public function projectURI(){
        $realPath = parse_url($this->completeURL(), PHP_URL_PATH);

        $paths = explode(BASE_URL_RELATIVE,$realPath);
        $projectURI = (count($paths) == 2) ? $paths[1] : "/" ;
        $projectURI = trim($projectURI,"/");

        $paginations = explode(":page",$projectURI);
        $projectURI = current($paginations);
        $page = (count($paginations) === 2) ? (int) end($paginations) : 1;
        Globals::setItem("pageIndex",$page);

        /*
        print_pre($projectURI);
        print_pre(BASE_URL_RELATIVE);
        print_pre($realPath);
        print_pre($paths);
        print_pre(explode(BASE_URL_RELATIVE,$realPath));
        die();
        */

        return $projectURI;

//        return $this->hyphenToCammelCase($projectURI);

    }

    public function hyphenToCammelCase(string $string){

        if(strlen($string) == 0){
            return $string;
        }

        if(ctype_upper($string[0])){
            $string = ucwords($string, "-");
        } else {
            $string = lcfirst(ucwords($string, "-")); //Preserving case
        }
        return str_replace("-", "", $string);

    }

    public function pageIndex(){
        return Globals::getItem("pageIndex") ?? 1;
    }

    public function completeURL(){
        return trim(REQUEST_SCHEME . "://" .$_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'],"/");
    }

    public function requestQueryString(){
        return $_SERVER['QUERY_STRING'];
    }

}