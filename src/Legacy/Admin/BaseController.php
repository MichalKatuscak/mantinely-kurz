<?php
/**
 * Zakladni "controller" administrace (martin 2017 – pokus o MVC).
 * Stranky psane pred 2017 jsou proceduralni (*.php v tomto adresari).
 */

namespace App\Legacy\Admin;

class BaseController
{
    protected $title = 'Administrace';

    public function __construct()
    {
        // zajisti, ze jsou nactene knihovny (kdyz se controller pouzije samostatne)
        if (!function_exists('legacy_db')) {
            require_once __DIR__ . '/../bootstrap.php';
        }
    }

    /**
     * Vyrenderuje PHP sablonu z templates/ a vrati HTML.
     */
    public function render($template, array $vars = array())
    {
        $file = LEGACY_TEMPLATES . '/' . $template . '.php';
        if (!file_exists($file)) {
            return '<p>Chybí šablona ' . h($template) . '</p>';
        }
        extract($vars);
        ob_start();
        include $file;

        return ob_get_clean();
    }

    /**
     * Sablona zabalena do layoutu (hlavicka, menu, paticka).
     */
    public function renderLayout($template, array $vars = array(), $title = null)
    {
        $content = $this->render($template, $vars);

        return $this->render('layout', array(
            'content' => $content,
            'title'   => $title !== null ? $title : $this->title,
            'flashes' => flash_messages(),
        ));
    }

    protected function param($name, $default = '')
    {
        return get_param($name, $default);
    }

    protected function post($name, $default = '')
    {
        return post_param($name, $default);
    }

    protected function redirect($url)
    {
        redirect($url);

        return '';
    }

    protected function notFound($msg = 'Nenalezeno')
    {
        $GLOBALS['LEGACY_STATUS'] = 404;

        return $this->renderLayout('partials/message', array('message' => $msg), 'Nenalezeno');
    }
}
