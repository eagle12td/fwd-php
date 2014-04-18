<?php
/**
 * Forward // PHP Template Framework
 *
 * @version  1.0.2
 * @link     https://getfwd.com
 * @license  http://www.apache.org/licenses/LICENSE-2.0
 */

namespace Forward;

class Controller
{
    /**
     * Index of invoked controller classes
     * @var array
     */
    public static $classes;

    /**
     * Index of invoked method results
     * @var array
     */
    public static $results;

    /**
     * Invoke a controller class/method
     *
     * @param  string $controllers
     * @param  array $params
     * @return mixed
     */
    public static function invoke($controllers, $params = null)
    {
        if (!$controllers) return;

        if (is_array($controllers))
        {
            $was_array = true;
        }
        else
        {
            $was_array = false;
            $controllers = array($controllers);
        }
        
        $vars = Template::engine()->get();

        foreach ($controllers as $name)
        {
            $controller = self::route($name, $vars['request']);

            if (!is_file($controller['path']))
            {
                $controller['path'] = $controller['extend']['path'];

                if (!is_file($controller['path']))
                {
                    throw new \Exception('Controller not found at '.$controller['path']);
                }
            }

            if ($was_array)
            {
                $results[] = self::invoke_method($controller, &$vars, $params);
            }
            else
            {
                $results = self::invoke_method($controller, &$vars, $params);
            }
        }

        Template::engine()->set_global($vars);

        return $results;
    }

    /**
     * Invoke a controller method
     *
     */
    public static function invoke_method($controller, &$vars = array(), $params = array())
    {
        if (!self::$classes)
        {
            spl_autoload_register('\\Forward\\Controller::autoload');
        }

        $class = "{$controller['namespace']}\\{$controller['class']}";
        self::$classes[$class] = $controller;

        if (!class_exists($class))
        {
            throw new \Exception($controller['class'].' not defined in '.$controller['path']);
        }

        $instance = new $class($params);
        $default_method = $controller['method'] ?: $instance->default;
        $method = $default_method ?: 'session';

        if (is_null($method))
        {
            return;
        }
        
        if (method_exists($instance, $method))
        {
            if (!array_key_exists($method, (array)self::$results))
            {
                foreach ((array)$params as $var => $value)
                {
                    $vars[$var] = $value;
                }
                foreach ((array)$vars as $var => $value)
                {
                    $instance->{$var} = $value;
                }

                $result = call_user_method_array($method, $instance, array($params));
                foreach ((array)$instance as $var => $value)
                {
                    $vars[$var] = $value;
                }

                return self::$results[$method] = $result;
            }
            else
            {
                self::$results[$method];
            }
        }
        else
        {
            if ($default_method)
            {
                throw new \Exception("Controller method '".$method."()' not defined in ".$controller['class']);
            }
        }

        return;
    }

    /**
     * Route to a controller by name
     *
     */
    public static function route($name, $request)
    {
        if (!$request) return;

        $parts = explode('/', ltrim($name, '/'));

        $name = Util\camelize($parts[0]);
        $namespace = "Forward\\".Util\camelize($request['template'])."Template";
        $extend_namespace = "Forward\\".Util\camelize($request['extend_template'])."Template";
        $class_name = $name.'Controller';
        $class_file = $class_name.'.php';
        $class_path = $request['template_path'].'/controllers/'.$class_file;
        $extend_class_path = $request['extend_template_path'].'/controllers/'.$class_file;
        $class_method = $parts[1] ? Util\underscore($parts[1]) : null;

        return array(
            'name' => $name,
            'namespace' => $namespace,
            'class' => $class_name,
            'file' => $class_file,
            'path' => $class_path,
            'method' => $class_method,
            'extend' => array(
                'namespace' => $extend_namespace,
                'path' => $extend_class_path
            )
        );
    }

    /**
     * Autoloader for template controllers
     *
     */
    public static function autoload($class_name)
    {
        $controller = self::$classes[$class_name];

        if (is_file($controller['path']))
        {
            // Include all controllers in this path, and also extend paths
            foreach (array($controller['extend'], $controller) as $ctrl)
            {
                foreach (glob(dirname($ctrl['path']).'/*Controller.php') as $controller_path)
                {
                    self::load($controller_path, $ctrl);
                }
            }
        }
    }

    /**
     * Load and evaluate a controller from a file
     *
     * @param  string $controller_path
     */
    public static function load($controller_path, $controller)
    {
        $class_contents = file_get_contents($controller_path);

        // Auto append controller namespace
        $class_contents = preg_replace(
            '/<\?php/',
            '<?php namespace '.$controller['namespace'].';',
            $class_contents,
            1
        );

        ob_start();
        try {
            $result = eval('?>'.$class_contents);
        }
        catch (\Exception $e)
        {
            $e_class = get_class($e);
            $message = $controller['class'].': '.$e_class.' "'.$e->getMessage()
                .'" in '.$controller_path.' on line '.$e->getLine();
            throw new \Exception($message, $e->getCode(), $e);
        }
        ob_end_clean();

        if ($result === false)
        {
            $error = error_get_last();
            $message = 'Parse error: '.$error['message']
                .' in '.$controller_path.' on line '.$error['line'];
            $lines = explode("\n", htmlspecialchars($class_contents));
            $eline = $error['line']-1;
            $lines[$eline] = '<b style="background-color: #fffed9">'.$lines[$eline].'</b>';
            $first_line = $eline > 5 ? $eline-5 : 0;
            $lines = array_slice($lines, $first_line, 11);
            foreach ($lines as $k => $v)
            {
                $lines[$k] = ($eline-4+$k).' '.$v;
            }
            $content = implode("\n", $lines);
            $message .= "<pre>{$content}</pre>";
            throw new \Exception($message);
        }
    }
}