<?php
/**
 * Most mezi Symfony a starou administraci (2024).
 *
 * Symfony routa /admin/legacy/{page} -> __invoke(). Stara administrace cte
 * superglobalni promenne, takze sem prekopirujeme query/post z Requestu,
 * spustime controller nebo proceduralni stranku pod ob_start() a vystup
 * vratime jako Response.
 *
 * Jedine misto v src/Legacy, ktere zna Symfony.
 */

namespace App\Legacy\Http;

use App\Legacy\Admin\AdminController;
use App\Legacy\Admin\CustomerController;
use App\Legacy\Admin\ExportController;
use App\Legacy\Admin\InvoiceController;
use App\Legacy\Admin\OrderController;
use App\Legacy\Admin\ProductController;
use App\Legacy\Admin\ReportController;
use App\Legacy\Admin\StockController;
use App\Legacy\Admin\UserController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class LegacyFrontController
{
    /**
     * Povolene stranky: page => array(trida, metoda) nebo nazev souboru v Admin/.
     */
    private static $pages = array(
        // "MVC" controllery (2017+)
        'dashboard'          => array(AdminController::class, 'dashboardAction'),
        'search'             => array(AdminController::class, 'searchAction'),
        'report'             => array(ReportController::class, 'monthlyAction'),
        'report_year'        => array(ReportController::class, 'yearAction'),
        'report_top'         => array(ReportController::class, 'topAction'),
        'orders'             => array(OrderController::class, 'listAction'),
        'order'              => array(OrderController::class, 'detailAction'),
        'customer_orders'    => array(OrderController::class, 'byCustomerAction'),
        'customers'          => array(CustomerController::class, 'listAction'),
        'customer'           => array(CustomerController::class, 'detailAction'),
        'customer_delete'    => array(CustomerController::class, 'deleteAction'),
        'customer_anonymize' => array(CustomerController::class, 'anonymizeAction'),
        'products'           => array(ProductController::class, 'listAction'),
        'products_bulk_price'=> array(ProductController::class, 'bulkPriceAction'),
        'product_toggle'     => array(ProductController::class, 'toggleAction'),
        'stock'              => array(StockController::class, 'listAction'),
        'stock_reservations' => array(StockController::class, 'reservationsAction'),
        'stock_inventory'    => array(StockController::class, 'inventoryAction'),
        'user_list'          => array(UserController::class, 'listAction'),
        'user_save'          => array(UserController::class, 'saveAction'),
        'user_delete'        => array(UserController::class, 'deleteAction'),
        'invoices'           => array(InvoiceController::class, 'listAction'),
        'invoice'            => array(InvoiceController::class, 'detailAction'),
        'invoice_issue'      => array(InvoiceController::class, 'issueAction'),
        'export'             => array(ExportController::class, 'indexAction'),
        'export_csv'         => array(ExportController::class, 'ordersAction'),
        'export_customers'   => array(ExportController::class, 'customersAction'),

        // proceduralni stranky (2014–2016)
        'order_edit'         => 'order_edit.php',
        'orders_bulk'        => 'orders.php',
        'order_list'         => 'order_list.php',
        'order_notes'        => 'order_notes.php',
        'unpaid_orders'      => 'unpaid_orders.php',
        'users'              => 'users.php',
        'login'              => 'login.php',
        'logout'             => 'logout.php',
        'stats'              => 'stats.php',
        'chart'              => 'chart.php',
        'monthly'            => 'monthly.php',
        'customer_edit'      => 'customer_edit.php',
        'customer_export'    => 'customer_export.php',
        'product_edit'       => 'product_edit.php',
        'newsletter'         => 'newsletter.php',
        'settings'           => 'settings.php',
        'audit'              => 'audit.php',
        'invoice_print'      => 'invoice_print.php',
        'stock_report'       => 'stock_report.php',
        'top_products'       => 'top_products.php',
        'suppliers'          => 'suppliers.php',
        'exchange_rates'     => 'exchange_rates.php',
        'sales_by_currency'  => 'sales_by_currency.php',
    );

    public function __invoke(Request $request, string $page = 'dashboard'): Response
    {
        require_once __DIR__ . '/../bootstrap.php';

        if (!isset(self::$pages[$page])) {
            return new Response('<h1>404</h1><p>Stránka neexistuje.</p>', 404);
        }

        // stara administrace cte superglobaly
        $_GET = $request->query->all();
        $_POST = $request->request->all();
        $_REQUEST = array_merge($_GET, $_POST);
        $_SERVER['REQUEST_METHOD'] = $request->getMethod();

        $GLOBALS['LEGACY_REDIRECT'] = null;
        $GLOBALS['LEGACY_STATUS'] = 200;
        $GLOBALS['LEGACY_CONTENT_TYPE'] = 'text/html; charset=utf-8';
        $GLOBALS['LEGACY_FILENAME'] = null;

        $target = self::$pages[$page];
        $level = ob_get_level();
        ob_start();
        try {
            if (is_array($target)) {
                $controller = new $target[0]();
                $result = call_user_func(array($controller, $target[1]));
                $html = ob_get_clean() . (is_string($result) ? $result : '');
            } else {
                $this->includePage(__DIR__ . '/../Admin/' . $target);
                $html = ob_get_clean();
            }
        } finally {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }

        if (!empty($GLOBALS['LEGACY_REDIRECT'])) {
            return new Response('', 302, array('Location' => $GLOBALS['LEGACY_REDIRECT']));
        }

        $response = new Response($html, (int) $GLOBALS['LEGACY_STATUS']);
        $response->headers->set('Content-Type', $GLOBALS['LEGACY_CONTENT_TYPE']);
        if (!empty($GLOBALS['LEGACY_FILENAME'])) {
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $GLOBALS['LEGACY_FILENAME'] . '"');
        }

        return $response;
    }

    /**
     * Include ve vlastnim scope, at si stranky neprepisuji promenne mostu.
     */
    private function includePage($file)
    {
        include $file;
    }
}
